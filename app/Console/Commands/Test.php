<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PHPHtmlParser\Dom;

class Test extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dev:test {file}';

    /**
     * The console command description.
     */
    protected $description = 'Test anything';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filename = $this->argument('file');

        $contents = file_get_contents($filename);

        $dom = new Dom;
        $dom->load($contents);
        dd($dom);
    }
}
