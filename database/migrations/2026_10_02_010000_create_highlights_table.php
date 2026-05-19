<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('highlights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            // W3C text-quote selector: the quote plus surrounding context, so the
            // highlight can be re-anchored even if the rendered markup changes.
            $table->text('exact');
            $table->string('prefix', 64)->default('');
            $table->string('suffix', 64)->default('');
            $table->string('color', 16);
            $table->text('note')->nullable();
            $table->jsonb('tags')->default('[]');
            $table->timestamps();

            $table->index(['article_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('highlights');
    }
};
