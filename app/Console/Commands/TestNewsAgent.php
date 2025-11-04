<?php

namespace App\Console\Commands;

use App\Neuron\Agents\NewsAgent;
use Illuminate\Console\Command;
use NeuronAI\Chat\Messages\UserMessage;

class TestNewsAgent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:news-agent';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the NewsAgent flow';


    /**
     * Execute the console command.
     */
    public function handle()
    {
        $response = NewsAgent::make()->chat(
            new UserMessage('Tell me about business news in nigeria, translate to german.')
            //     new UserMessage('What is happening in football news today?')
        );

        echo  $response->getContent();
    }
}