# Cowork task: WhatsApp rooms → Truehold

Run once a day (e.g. 09:30). Paste everything below the line as the task.

---

Update the "WhatsApp rooms" tab of the Room targets Google Sheet
(https://docs.google.com/spreadsheets/d/1aId9bPdtDHuuTo_8FMlg2JgZsb0tXiFHYwZVtVvHaRc)
from three WhatsApp groups in WhatsApp Web. Only copy what the messages say;
the Truehold site applies all the rules (deposits, prices, expiry).

If the tab does not exist, create it with exactly this header row:

Agency | Status | Area | Street | Postcode | Room | Price | Per | Price for 2 | Room type | Available from | Posted | Last seen | Photos link | Bathrooms | Notes

One row per room. A room is the same room when Agency + Postcode + Street +
Room match; update that row instead of adding a new one. Dates as DD/MM/YYYY.

**Keep it cheap:** read only messages newer than the latest "Last seen" date
for that agency in the tab. Read messages as page text (a small script over
the chat's message elements), not screenshots; screenshots cost far more.
WhatsApp Web keeps only part of a chat loaded, so collect while scrolling
and merge. It may not load history older than about a week ("get older
messages from your phone"); that is fine: Vic and Fab repost full lists.
Do not open photos except to count bathrooms when it is obvious.

**Never copy** phone numbers, people's names, or anything about tenants.
Agents sometimes post a tenant's profile (name, date of birth, email,
nationality): skip those messages entirely.

**Writing to the sheet:** paste rows as tab-separated text in one go (typing
Tab into Sheets puts everything in one cell). Dates as DD/MM/YYYY; the site
reads them either way.

## Fausto — group "Fausto-Truehold group" (Agency: `Fausto`)

They post a list ("NEW LIST 24.09.pdf" and a picture of it) almost every day.
The newest list is the truth:
- Every room on the newest list: Status `Available`, Last seen = the list's date.
- A room crossed out with a red X on the picture: Status `Let`.
- A Fausto row that is not on the newest list: Status `Let`.
Each entry reads like "TOTTENHAM HALE / Havelock Road, N17 9DR / £825 Double
for 1 person / Available Now | Room B" → Area `Tottenham Hale`, Street
`Havelock Road`, Postcode `N17 9DR`, Room `B`, Price `825`, Per `pcm`,
Room type `Double`, Available from `Now`.

## Vic — group "Vic-Truehold" (Agency: `Vic`)

Rooms come as single messages, e.g. "EDMONTON / TRAMWAY AVENUE, N9 8PE /
LARGE DOUBLE ROOM / £170pw FOR 1 PERSON / £195pw FOR 2 PERSONS / AVAILABLE NOW".
→ Price `170`, Per `pw`, Price for 2 `195`. Posted = the message date; if the
room is posted again, set Last seen to the new date. There is no X: if anyone
in the chat says that room is taken / let / gone, set Status `Let`. A room not
posted again for two weeks drops off the site by itself.

Several rooms on one street with no room letter: name them `Double 1`,
`Double 2`, `Single`, `Master` so each is its own row.

## Fab — group "Fab- Truehold" (Agency: `Fab`)

Same as Vic, two weeks. Fab mostly offers rooms in reply to our agents'
questions ("anything for 650?" → "Yes, Seven Sisters, single room, 6
Crowland Road N15"). Take the price from the question and say so in Notes:
`Price implied: offered for "anything for 650?"`. Use whatever postcode is
given, even just the district (N15). Questions alone are not rooms.

## Photos (after the sheet, each group)

Log in to https://truehold.yaenlinea.co in the same browser first. In each
group, run the script in `docs/whatsapp-photos.js` (paste it into the page),
then `thPhotos.button('fausto')` (or `'vic'`, `'fab'`) and click the green
"Send … photos to Truehold" button. Scroll up with the mouse to load older
rooms and click again; photos already sent are skipped. It pairs each album
with the room text posted beside it (within two minutes) and leaves out
anything it cannot pair. **Never press keys in WhatsApp Web** (Page Up
etc.): they are typed into the message box. Never send a message.

Older rooms: scrolling stops about a week back. For a room on the sheet
with no photos, open the group's own search (magnifier in the chat header),
check the cursor is in that search box, type the street, click the newest
result: WhatsApp jumps to that post and loads it; then click the button
again. This found every Fausto room.

## Every run

- Photos link: leave empty (photos go through the script above).
- Bathrooms: leave empty (the site assumes 1) unless the photos clearly show more.
- Finish with one line: rows added, updated, marked Let, per agency.
