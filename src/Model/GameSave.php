<?php
namespace LadyByron\Game\Model;

use Flarum\Database\AbstractModel;

/**
 * @property int    $id
 * @property int    $user_id
 * @property string $game_slug
 * @property string $slot
 * @property int    $rev
 * @property string $state_json
 * @property array|null $meta_json
 * @property string|null $story_hash
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class GameSave extends AbstractModel
{
    protected $table = 'ladybyron_game_saves';

    protected $casts = [
        'meta_json' => 'array',
        'rev'       => 'integer',
        'user_id'   => 'integer',
    ];

    protected $fillable = [
        'user_id', 'game_slug', 'slot', 'rev', 'state_json', 'meta_json', 'story_hash',
    ];
}
