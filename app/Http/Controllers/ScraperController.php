<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class ScraperController extends Controller
{
    public function index()
    {
        // Check if profiles.csv exists
        $profilesPath = base_path('profiles.csv');
        $profiles = [];
        if (File::exists($profilesPath)) {
            $profiles = array_filter(array_map('trim', file($profilesPath, FILE_IGNORE_NEW_LINES)));
        }

        // Check if newscrape.csv exists
        $scrapePath = base_path('newscrape.csv');
        $scrapeExists = File::exists($scrapePath);
        $scrapeData = [];
        if ($scrapeExists) {
            $csvData = array_map('str_getcsv', file($scrapePath));
            $headers = array_shift($csvData);
            $scrapeData = array_slice($csvData, 0, 5); // Show first 5 rows
        }

        return view('admin.scraper.index', compact('profiles', 'scrapeExists', 'scrapeData'));
    }

    public function addProfile(Request $request)
    {
        $request->validate([
            'profile_url' => 'required|url'
        ]);

        $profilesPath = base_path('profiles.csv');
        $url = $request->profile_url;

        // Add to profiles.csv
        file_put_contents($profilesPath, $url . "\n", FILE_APPEND | LOCK_EX);

        return redirect()->route('admin.scraper.index')->with('success', 'Profile URL added successfully!');
    }

    public function removeProfile(Request $request)
    {
        $request->validate([
            'profile_url' => 'required'
        ]);

        $profilesPath = base_path('profiles.csv');
        $urlToRemove = $request->profile_url;

        if (File::exists($profilesPath)) {
            $profiles = array_filter(array_map('trim', file($profilesPath, FILE_IGNORE_NEW_LINES)));
            $profiles = array_filter($profiles, function($url) use ($urlToRemove) {
                return $url !== $urlToRemove;
            });
            
            file_put_contents($profilesPath, implode("\n", $profiles) . "\n");
        }

        return redirect()->route('admin.scraper.index')->with('success', 'Profile URL removed successfully!');
    }

    public function runScraper()
    {
        try {
            // Create the Python scraper script
            $pythonScript = $this->createPythonScraper();
            
            // Try to find Python executable
            $pythonPath = $this->findPythonExecutable();
            
            if (!$pythonPath) {
                return redirect()->route('admin.scraper.index')->with('error', 'Python not found. Please install Python and add it to your system PATH, or use the PHP scraper instead.');
            }
            
            // Run the Python scraper
            $result = Process::run($pythonPath . ' ' . $pythonScript);
            
            if ($result->successful()) {
                return redirect()->route('admin.scraper.index')->with('success', 'Python scraper completed successfully! Check the results below.');
            } else {
                return redirect()->route('admin.scraper.index')->with('error', 'Python scraper failed: ' . $result->errorOutput() . '. Try using the PHP scraper instead.');
            }
        } catch (\Exception $e) {
            return redirect()->route('admin.scraper.index')->with('error', 'Error running Python scraper: ' . $e->getMessage() . '. Try using the PHP scraper instead.');
        }
    }
    
    private function findPythonExecutable()
    {
        // Common Python paths on Windows
        $possiblePaths = [
            'C:\\Users\\Ali\\AppData\\Local\\Programs\\Python\\Python311\\python.exe',
            'C:\\Users\\Ali\\AppData\\Local\\Programs\\Python\\Python312\\python.exe',
            'C:\\Python311\\python.exe',
            'C:\\Python312\\python.exe',
            'python3',
            'python',
            'py',
            'python.exe'
        ];
        
        foreach ($possiblePaths as $path) {
            try {
                $result = Process::run($path . ' --version');
                if ($result->successful()) {
                    return $path;
                }
            } catch (\Exception $e) {
                // Continue to next path
            }
        }
        
        return null;
    }

    public function importData()
    {
        try {
            // Try different PHP paths
            $phpPaths = [
                'C:\\xampp\\php\\php.exe',
                'php',
                'C:\\Program Files\\PHP\\php.exe',
                'C:\\php\\php.exe'
            ];
            
            $phpPath = null;
            foreach ($phpPaths as $path) {
                try {
                    $result = Process::run($path . ' --version');
                    if ($result->successful()) {
                        $phpPath = $path;
                        break;
                    }
                } catch (\Exception $e) {
                    // Continue to next path
                }
            }
            
            if (!$phpPath) {
                return redirect()->route('admin.scraper.index')->with('error', 'PHP not found. Please check your PHP installation.');
            }
            
            // Run the Laravel import command with full PHP path
            $result = Process::run($phpPath . ' artisan properties:import-newscrape');
            
            if ($result->successful()) {
                return redirect()->route('admin.scraper.index')->with('success', 'Data imported successfully! ' . $result->output());
            } else {
                return redirect()->route('admin.scraper.index')->with('error', 'Import failed: ' . $result->errorOutput());
            }
        } catch (\Exception $e) {
            return redirect()->route('admin.scraper.index')->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    private function createPythonScraper()
    {
        $scriptPath = base_path('scraper_script.py');
        
        $pythonScript = '
import requests
from bs4 import BeautifulSoup
import pandas as pd
import csv
import re
import time
import json

def clean_text(text):
    """Clean and normalize text data (for titles, small fields)"""
    if not text or text == "N/A":
        return None
    text = re.sub(r\'\\s+\', \' \', text.strip())  # remove extra whitespace
    text = re.sub(r\'<[^>]+>\', \'\', text)       # remove HTML tags
    if len(text) > 500:
        text = text[:500] + "..."
    return text

def extract_rich_text(elem):
    """Extract text while preserving emojis and newlines from <br> tags (for description only)"""
    if not elem:
        return None
    for br in elem.find_all("br"):
        br.replace_with("\\n")
    text = elem.get_text()
    text = text.strip()
    return text if text else None

def extract_price(price_text):
    """Extract and normalize price information (convert pw → monthly if needed)"""
    if not price_text or price_text == "N/A":
        return None

    price_match = re.search(r\'£?(\\d+(?:,\\d+)?(?:\\.\\d{2})?)\', str(price_text))
    if price_match:
        price = price_match.group(1).replace(\',\', \'\')
        try:
            price = float(price)
            text_lower = price_text.lower()

            if "pw" in text_lower or "per week" in text_lower:
                # Convert weekly → monthly (average 52 weeks / 12 months)
                price = round(price * 52 / 12)

            else:
                # Assume price is already per month
                price = round(price)

            return int(price)
        except:
            return None
    return None

def extract_coordinates(lat_text, lon_text):
    """Extract and validate coordinates"""
    try:
        lat = float(lat_text) if lat_text and lat_text != "N/A" else None
        lon = float(lon_text) if lon_text and lon_text != "N/A" else None
        if lat is not None and (-90 <= lat <= 90):
            lat = round(lat, 8)
        else:
            lat = None
        if lon is not None and (-180 <= lon <= 180):
            lon = round(lon, 8)
        else:
            lon = None
        return lat, lon
    except:
        return None, None

def extract_feature_list(soup):
    """Extracts key-value pairs from feature-list <dl> blocks"""
    features = {}
    for dl in soup.find_all("dl", class_="feature-list"):
        keys = dl.find_all("dt", class_="feature-list__key")
        vals = dl.find_all("dd", class_="feature-list__value")

        for k, v in zip(keys, vals):
            key = clean_text(k.get_text()) if k else None
            val = clean_text(v.get_text()) if v else None

            # Handle tick/cross spans ("Yes"/"No")
            if v and v.find("span", class_="tick"):
                val = "Yes"
            elif v and v.find("span", class_="cross"):
                val = "No"

            if key:
                features[key] = val
    return features

def scrape_listing_advanced(url):
    try:
        headers = {
            \'User-Agent\': \'Mozilla/5.0 (Windows NT 10.0; Win64; x64)\',
        }
        resp = requests.get(url, headers=headers)
        resp.raise_for_status()
        soup = BeautifulSoup(resp.text, "html.parser")

        # Title
        title = "N/A"
        title_elem = soup.find("h1") or soup.find("h2") or soup.find("title")
        if title_elem:
            title = clean_text(title_elem.get_text())

        # Agent / Landlord name
        agent_name = None
        agent_elem = soup.find("strong", class_="profile-photo__name")
        if agent_elem:
            agent_name = clean_text(agent_elem.get_text())

       # Extract location (always the 2nd <li> inside .key-features)
        location = None
        key_features = soup.find("ul", class_="key-features")
        if key_features:
         items = key_features.find_all("li", class_="key-features__feature")
         if len(items) >= 2:
          location = items[1].get_text(strip=True)  # ✅ "Devons Road"

        # Latitude / Longitude
        latitude, longitude = "N/A", "N/A"
        script_tags = soup.find_all("script")
        for script in script_tags:
            if script.string:
                script_content = script.string
                lat_match = re.search(r\'latitude["\\s]*:["\\s]*"?([0-9.-]+)"?\', script_content)
                lon_match = re.search(r\'longitude["\\s]*:["\\s]*"?([0-9.-]+)"?\', script_content)
                if lat_match:
                    latitude = lat_match.group(1)
                if lon_match:
                    longitude = lon_match.group(1)
                location_match = re.search(
                    r\'location["\\s]*:["\\s]*{[^}]*latitude["\\s]*:["\\s]*"?([0-9.-]+)"?[^}]*longitude["\\s]*:["\\s]*"?([0-9.-]+)"?\',
                    script_content
                )
                if location_match:
                    latitude, longitude = location_match.group(1), location_match.group(2)

        # Photos (only inside photo-gallery containers)
        all_photo_urls = []

        # ✅ Find the main photo-gallery container
        photo_gallery = soup.select_one("div.photo-gallery")
        if photo_gallery:
            # Extract from main image wrapper
            main_gallery = photo_gallery.select_one("dl.photo-gallery__main-image-wrapper")
            if main_gallery:
                # Check for links with data-src or href attributes (try class first, then any link)
                main_links = main_gallery.find_all("a", class_="photo-gallery__thumbnail-link")
                if not main_links:
                    # Fallback: find any links in the main gallery
                    main_links = main_gallery.find_all("a", href=re.compile(r"photos2\\.spareroom\\.co\\.uk"))
                
                for link in main_links:
                    # Prefer data-src attribute (more reliable), then href
                    photo_url = link.get("data-src") or link.get("href")
                    if photo_url:
                        # Ensure it\'s a full URL and points to large version
                        if not photo_url.startswith("http"):
                            photo_url = "https://" + photo_url.lstrip("/")
                        # Convert square/medium to large if needed
                        photo_url = re.sub(r"/\w+/(\d+/\d+/\d+\.jpg)", r"/large/\1", photo_url)
                        if "photos2.spareroom.co.uk" in photo_url and photo_url not in all_photo_urls:
                            all_photo_urls.append(photo_url)
                
                # Also check the img tag src attribute in main image
                main_img = main_gallery.find("img", class_="photo-gallery__main-image")
                if main_img:
                    img_src = main_img.get("src")
                    if img_src:
                        if not img_src.startswith("http"):
                            img_src = "https://" + img_src.lstrip("/")
                        # Convert to large version
                        img_src = re.sub(r"/\w+/(\d+/\d+/\d+\.jpg)", r"/large/\1", img_src)
                        if "photos2.spareroom.co.uk" in img_src and img_src not in all_photo_urls:
                            all_photo_urls.append(img_src)

            # ✅ Extract from thumbnail gallery container
            thumb_gallery = photo_gallery.select_one("div.photo-gallery__thumbnails")
            if thumb_gallery:
                # Find all links in thumbnail gallery (try class first, then any link)
                thumb_links = thumb_gallery.find_all("a", class_="photo-gallery__thumbnail-link")
                if not thumb_links:
                    # Fallback: find any links in the thumbnail gallery
                    thumb_links = thumb_gallery.find_all("a", href=re.compile(r"photos2\\.spareroom\\.co\\.uk"))
                
                for link in thumb_links:
                    # Prefer data-src attribute (more reliable), then href
                    photo_url = link.get("data-src") or link.get("href")
                    if photo_url:
                        # Ensure it\'s a full URL and points to large version
                        if not photo_url.startswith("http"):
                            photo_url = "https://" + photo_url.lstrip("/")
                        # Convert square/medium to large if needed
                        photo_url = re.sub(r"/\w+/(\d+/\d+/\d+\.jpg)", r"/large/\1", photo_url)
                        if "photos2.spareroom.co.uk" in photo_url and photo_url not in all_photo_urls:
                            all_photo_urls.append(photo_url)

        first_photo_url = all_photo_urls[0] if all_photo_urls else None
        photo_count = len(all_photo_urls)
        all_photos = ", ".join(all_photo_urls) if all_photo_urls else None

        # Price
        price = None
        price_selectors = [".price", ".rent", ".amount", "[class*=\'price\']", "[class*=\'rent\']", "[class*=\'amount\']"]
        for selector in price_selectors:
            try:
                elements = soup.select(selector)
                for elem in elements:
                    text = elem.get_text(strip=True)
                    if \'£\' in text:
                        price = extract_price(text)
                        if price:
                            break
            except:
                continue

        # ✅ Description (preserve emojis + newlines)
        description = None
        detaildesc_elem = soup.find("p", class_="detaildesc")
        if detaildesc_elem:
            description = extract_rich_text(detaildesc_elem)
        else:
            feature_desc_body = soup.find("div", class_="feature__description-body")
            if feature_desc_body:
                description = extract_rich_text(feature_desc_body)
            else:
                desc_selectors = [
                    ".description", ".details", ".content",
                    "[class*=\'description\']", "[class*=\'details\']",
                    "[class*=\'content\']", "p", ".listing-details"
                ]
                for selector in desc_selectors:
                    try:
                        elements = soup.select(selector)
                        for elem in elements:
                            text = extract_rich_text(elem)
                            if text and len(text) > 50:
                                description = text
                                break
                        if description:
                            break
                    except:
                        continue

        # Property type
        property_type = None
        type_keywords = ["room", "bedroom", "studio", "flat", "apartment", "house", "en-suite", "ensuite"]
        search_text = (str(title) + " " + str(description)).lower()
        for keyword in type_keywords:
            if keyword in search_text:
                property_type = keyword.title()
                break

        # ✅ Extract structured features
        features = extract_feature_list(soup)
        available_date = features.get("Available")
        min_term = features.get("Minimum term")
        max_term = features.get("Maximum term")
        deposit = features.get("Deposit")
        bills_included = features.get("Bills included?")
        furnishings = features.get("Furnishings")
        parking = features.get("Parking")
        garden = features.get("Garden/patio")
        broadband = features.get("Broadband included")
        housemates = features.get("# housemates")
        total_rooms = features.get("Total # rooms")
        smoker = features.get("Smoker?")
        pets = features.get("Any pets?")
        occupation = features.get("Occupation")
        gender = features.get("Gender")
        couples_ok = features.get("Couples OK?")
        smoking_ok = features.get("Smoking OK?")
        pets_ok = features.get("Pets OK?")
        pref_occupation = features.get("Occupation")  # new housemate occupation
        references = features.get("References?")
        min_age = features.get("Min age")
        max_age = features.get("Max age")

        # Final result dictionary
        lat, lon = extract_coordinates(latitude, longitude)
        result = {
            "url": url,
            "title": title,
            "agent_name": agent_name,
            "location": location,
            "latitude": lat,
            "longitude": lon,
            "status": "available",
            "price": price,
            "description": description,  # ✅ emojis + newlines preserved
            "property_type": property_type,
            "available_date": available_date,
            "min_term": min_term,
            "max_term": max_term,
            "deposit": deposit,
            "bills_included": bills_included,
            "furnishings": furnishings,
            "parking": parking,
            "garden": garden,
            "broadband": broadband,
            "housemates": housemates,
            "total_rooms": total_rooms,
            "smoker": smoker,
            "pets": pets,
            "occupation": occupation,
            "gender": gender,
            "couples_ok": couples_ok,
            "smoking_ok": smoking_ok,
            "pets_ok": pets_ok,
            "pref_occupation": pref_occupation,
            "references": references,
            "min_age": min_age,
            "max_age": max_age,
            "photo_count": photo_count,
            "first_photo_url": first_photo_url,
            "all_photos": all_photos,
            "photos": json.dumps(all_photo_urls) if all_photo_urls else None,
        }

        return result
    except Exception as e:
        print(f"Failed to scrape {url}: {e}")
        return None

def main():
    # First, scrape profile URLs to get listing URLs
    print("Step 1: Scraping profile URLs to get listing URLs...")
    
    profiles = []
    try:
        with open("profiles.csv", "r") as f:
            profiles = [line.strip() for line in f if line.strip()]
    except FileNotFoundError:
        print("profiles.csv not found!")
        return
    
    base_url = "https://www.spareroom.co.uk"
    headers = {"User-Agent": "Mozilla/5.0"}
    all_listings = []

    for profile_url in profiles:
        try:
            print(f"Scraping profile: {profile_url}")
            response = requests.get(profile_url, headers=headers)
            response.raise_for_status()
            soup = BeautifulSoup(response.text, "html.parser")

            # Extract links from listing cards
            for a in soup.find_all("a", class_="listing-card__link"):
                href = a.get("href")
                if href:
                    full_url = href if href.startswith("http") else base_url + href
                    all_listings.append(full_url)
        except Exception as e:
            print(f"Error fetching {profile_url}: {e}")

    print(f"Found {len(all_listings)} listings to scrape")
    
    # Now scrape each listing
    print("Step 2: Scraping individual listings...")
    results = []
    
    for i, url in enumerate(all_listings, 1):
        print(f"Scraping {i}/{len(all_listings)}: {url}")
        data = scrape_listing_advanced(url)
        if data:
            results.append(data)
        time.sleep(1)  # be polite

    # Save results
    database_columns = [
        "url", "title", "agent_name", "location", "latitude", "longitude", "status", "price",
        "description", "property_type", "available_date", "min_term", "max_term",
        "deposit", "bills_included", "furnishings", "parking", "garden",
        "broadband", "housemates", "total_rooms", "smoker", "pets", "occupation",
        "gender", "couples_ok", "smoking_ok", "pets_ok", "pref_occupation",
        "references", "min_age", "max_age", "photo_count", "first_photo_url",
        "all_photos", "photos"
    ]

    output_filename = "newscrape.csv"
    with open(output_filename, "w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=database_columns)
        writer.writeheader()
        writer.writerows(results)

    print(f"Scraping complete. Results saved to {output_filename}")
    print(f"Successfully scraped {len(results)} properties")

if __name__ == "__main__":
    main()
';

        file_put_contents($scriptPath, $pythonScript);
        return $scriptPath;
    }
}
