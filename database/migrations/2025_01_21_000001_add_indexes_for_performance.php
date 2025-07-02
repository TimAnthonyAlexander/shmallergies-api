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
        Schema::table('products', function (Blueprint $table) {
            // Add index for name field (commonly searched)
            $table->index('name');
            // UPC code already has unique index, but let's ensure it's optimized
            $table->index('upc_code');
            // Add index for created_at for ordering
            $table->index('created_at');
        });

        Schema::table('ingredients', function (Blueprint $table) {
            // Add index for product_id foreign key
            $table->index('product_id');
            // Add index for title for searching
            $table->index('title');
        });

        Schema::table('allergens', function (Blueprint $table) {
            // Add index for ingredient_id foreign key
            $table->index('ingredient_id');
            // Add index for name for searching
            $table->index('name');
        });

        Schema::table('user_allergies', function (Blueprint $table) {
            // Add index for user_id foreign key
            $table->index('user_id');
            // Add index for allergy_text for searching
            $table->index('allergy_text');
        });

        Schema::table('users', function (Blueprint $table) {
            // Email already has unique index, but let's ensure proper indexing
            $table->index('email');
            $table->index('email_verified_at');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropIndex(['upc_code']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
            $table->dropIndex(['title']);
        });

        Schema::table('allergens', function (Blueprint $table) {
            $table->dropIndex(['ingredient_id']);
            $table->dropIndex(['name']);
        });

        Schema::table('user_allergies', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['allergy_text']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropIndex(['email_verified_at']);
            $table->dropIndex(['created_at']);
        });
    }
}; 