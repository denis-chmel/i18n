<?php namespace App\Models;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Class BaseModel
 *
 * @mixin QueryBuilder
 * @mixin EloquentBuilder
 * @property integer $id
 * @method static $this find($id, $columns = ['*'])
 * @method static $this findOrFail($id, $columns = ['*'])
 * @method static $this first($columns = [])
 * @method static $this firstOrNew($attributes, $values = [])
 * @method static Collection|$this[] all($columns = ['*'])
 * @method static $this findOrNew($attributes, $values = [])
 * @method static $this updateOrCreate($attributes, $values = [])
 * @method static $this create($attributes = [])
 * @method static $this firstOrCreate($attributes, $values = [])
 * @method static $this query()
 */
class BaseModel extends \Eloquent
{
    /**
     * @return int|null
     */
    public function getId()
    {
        return $this->id;
    }
}
