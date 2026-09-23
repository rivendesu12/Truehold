<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What agents ask Sigou and what they do with the answer, so the assistant
 * can be improved from real use: zero-result briefs, misreadings, which
 * section gets clicked. Our own database; no cost. Pruned after 180 days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('session_key', 16)->nullable()->index();
            $table->string('kind', 16)->index();
            $table->text('query');
            $table->json('filters')->nullable();
            $table->boolean('refined')->default(false);
            $table->unsignedSmallInteger('best')->default(0);
            $table->unsignedSmallInteger('other')->default(0);
            $table->unsignedSmallInteger('wild')->default(0);
            $table->text('sigou')->nullable();
            $table->json('usage')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->json('clicks')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_interactions');
    }
};
