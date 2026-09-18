<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            $table->string('catalog_slug')->nullable();
            $table->string('source')->default('upload');
            $table->string('category')->nullable();
            $table->timestamp('installed_from_catalog_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            $table->dropColumn([
                'catalog_slug',
                'source',
                'category',
                'installed_from_catalog_at',
            ]);
        });
    }
};
