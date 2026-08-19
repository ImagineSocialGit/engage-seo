<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 190)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->boolean('indexable')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 190)->unique();
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->json('content');
            $table->string('author_name')->nullable();
            $table->boolean('featured')->default(false)->index();
            $table->string('featured_image_asset')->nullable();
            $table->text('featured_image_alt')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->boolean('indexable')->default(true);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('blog_category_post', function (Blueprint $table): void {
            $table->foreignId('blog_category_id')
                ->constrained('blog_categories')
                ->cascadeOnDelete();
            $table->foreignId('blog_post_id')
                ->constrained('blog_posts')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique([
                'blog_category_id',
                'blog_post_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_category_post');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('blog_categories');
    }
};