<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Availability as reported by the source advert, not by the feed.
     *
     * The feed's own status field does not track lettings: a room can be
     * status=available there while SpareRoom shows "The advertiser is not
     * currently accepting applications". This table caches what the advert
     * itself says so listings can be hidden without slowing page loads.
     */
    public function up(): void
    {
        Schema::create('property_availability', function (Blueprint $table) {
            $table->id();
            $table->string('listing_id')->unique();
            $table->text('url');
            $table->boolean('accepting_applications')->default(true);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->index(['accepting_applications', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_availability');
    }
};
