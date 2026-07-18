<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('slug')->unique()->nullable()->after('title');
            $table->text('short_description')->nullable()->after('description');
            $table->string('category')->nullable()->after('short_description');
            $table->string('difficulty')->nullable()->after('category');
            $table->string('language')->nullable()->after('difficulty');
            $table->string('meta_title')->nullable()->after('language');
            $table->text('meta_description')->nullable()->after('meta_title');
            $table->string('keywords')->nullable()->after('meta_description');
            $table->boolean('is_free')->default(false)->after('keywords');
            $table->decimal('discount_price', 8, 2)->nullable()->after('price');
            $table->boolean('include_in_subscription')->default(false)->after('discount_price');
            $table->string('status')->default('Draft')->after('is_published');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn([
                'slug', 'short_description', 'category', 'difficulty', 'language',
                'meta_title', 'meta_description', 'keywords', 'is_free',
                'discount_price', 'include_in_subscription', 'status'
            ]);
        });
    }
};
