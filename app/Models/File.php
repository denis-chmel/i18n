<?php namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\File
 *
 * @mixin \Eloquent
 * @property integer $id
 * @property string $name
 * @property string $contents
 * @property array $texts
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $deleted_at
 */
class File extends BaseModel
{
    use SoftDeletes;
    use HasTimestamps;

    protected $dates = [
        'deleted_at',
    ];

    protected $casts = [
        'texts' => 'array',
    ];

    protected $guarded = [
        'id',
    ];

    public function getName()
    {
        return trim($this->name);
    }

    public function getContents()
    {
        return trim($this->contents);
    }

    public function getTexts()
    {
        if (null === $this->texts) {
            $contents = $this->getContents();

            if (starts_with($contents, chr(239).chr(187).chr(191))) {
                $contents = substr($contents, 3);
            }

            $contents = json_decode($contents, JSON_OBJECT_AS_ARRAY);
            $texts = [];
            foreach ($contents['Paragraphs'] as $para) {
                $text = '';
                foreach ($para['Lines'] as $line) {
                    $text .= $line['Text'] . PHP_EOL;
                }
                $texts[] = strip_tags($text);
            }

            $this->texts = $texts;
            $this->save();
        }
        return $this->texts;
    }
}
