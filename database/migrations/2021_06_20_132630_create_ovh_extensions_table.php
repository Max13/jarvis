<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOvhExtensionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ovh_extensions', function (Blueprint $table) {
            $table->id();
            $table->string('tld')->index();
            $table->decimal('register')->nullable();
            $table->decimal('renew')->nullable();
            $table->decimal('transfer')->nullable();
            $table->decimal('restore')->nullable();
            $table->tinyInteger('redemption')->unsigned()->nullable();
            $table->json('options')->default(new Expression('(json_object())'));
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ovh_extensions');
    }
}
