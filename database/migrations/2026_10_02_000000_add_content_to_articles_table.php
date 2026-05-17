<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('byline')->nullable()->after('title');
            $table->longText('content_html')->nullable()->after('excerpt');
            $table->longText('content_text')->nullable()->after('content_html');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['byline', 'content_html', 'content_text']);
        });
    }
};
