<?php

use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTableIfNotExists('ladybyron_game_saves', function (Blueprint $table) {

        $schema->create('ladybyron_game_saves', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('game_slug', 100);
            $table->string('slot', 50);
            $table->unsignedInteger('rev')->default(0);
            $table->longText('state_json');
            $table->text('meta_json')->nullable();
            $table->string('story_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'game_slug', 'slot'], 'ux_user_game_slot');
            $table->index(['user_id', 'game_slug']);
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('ladybyron_game_saves');
    },
];
