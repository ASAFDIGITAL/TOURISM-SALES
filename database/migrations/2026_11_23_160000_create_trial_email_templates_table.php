<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trial_email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('step', 32)->unique(); // day_0, day_7, day_11, day_13
            $table->unsignedInteger('sort_order')->default(1);
            $table->string('subject_he', 255);
            $table->string('subject_ar', 255)->nullable();
            $table->string('subject_en', 255)->nullable();
            $table->string('heading_he', 255);
            $table->string('heading_ar', 255)->nullable();
            $table->string('heading_en', 255)->nullable();
            $table->text('intro_he');
            $table->text('intro_ar')->nullable();
            $table->text('intro_en')->nullable();
            $table->json('highlights_he')->nullable();
            $table->json('highlights_ar')->nullable();
            $table->json('highlights_en')->nullable();
            $table->string('cta_label_he', 120);
            $table->string('cta_label_ar', 120)->nullable();
            $table->string('cta_label_en', 120)->nullable();
            $table->string('cta_path', 160)->default('/agent');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sent_count')->default(0);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_email_templates');
    }
};
