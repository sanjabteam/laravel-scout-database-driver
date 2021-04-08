<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSanjabScoutDriverTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $connection = config('scout.database.connection');
        Schema::connection($connection)->create('sanjab_searchable_words', function (Blueprint $table) {
            $table->id();
            $table->string('word', 191)->index()->unique();
        });
        Schema::connection($connection)->create('sanjab_searchable_objects', function (Blueprint $table) {
            $table->id();
            $table->string('object_id', 191)->index();
            $table->string('type', 191)->index();
            $table->longText('meta')->nullable();
            $table->longText('scout_meta')->nullable();

            $table->unique(['type', 'object_id']);
        });
        Schema::connection($connection)->create('sanjab_searchable_object_words', function (Blueprint $table) {
            $table->foreignId('searchable_object_id')->constrained('sanjab_searchable_objects')->onDelete('cascade')->onUpdate('cascade');
            $table->foreignId('searchable_word_id')->constrained('sanjab_searchable_words')->onDelete('cascade')->onUpdate('cascade');
            $table->boolean('full')->default(0);

            $table->unique(['searchable_object_id', 'searchable_word_id'], 'object_word_id_unique');
            $table->primary(['searchable_object_id', 'searchable_word_id'], 'object_word_id_primary');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $connection = config('scout.database.connection');
        Schema::connection($connection)->dropIfExists('sanjab_searchable_object_words');
        Schema::connection($connection)->dropIfExists('sanjab_searchable_objects');
        Schema::connection($connection)->dropIfExists('sanjab_searchable_words');
    }
}
