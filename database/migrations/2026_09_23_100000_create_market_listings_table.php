<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wildcards: rooms SpareRoom agents advertise as free to contact. Crawled
 * every couple of days, offered only in Sigou's search, never in the
 * listings agents browse. `token` is what our URLs use, so a shared link
 * never reveals the SpareRoom advert number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_listings', function (Blueprint $table) {
            $table->id();
            $table->string('spareroom_id')->unique();
            $table->string('token', 32)->unique();
            $table->string('agency')->nullable();
            $table->string('phone')->nullable();
            $table->longText('data');
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_listings');
    }
};
