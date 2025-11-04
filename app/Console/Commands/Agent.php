<?php

namespace App\Console\Commands;

use App\Neuron\YoutubeAgent;
use Illuminate\Console\Command;
use NeuronAI\Chat\Messages\UserMessage;

class Agent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:agent';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // $response = YoutubeAgent::make()->chat(
        //     new UserMessage("Hi, who are you?")
        // );

        dd($response->getContent());
    }
}
