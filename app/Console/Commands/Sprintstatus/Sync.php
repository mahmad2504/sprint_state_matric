<?php
namespace App\Console\Commands\Sprintstatus;

use Illuminate\Console\Command;
use App\Apps\Sprintstatus\Sprintstatus;
use App\Email;
class Sync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sprintstatus:sync {--rebuild=0} {--force=0} {--email=2} {--email_resend=0} {--sprint=null}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
	
    public function __construct()
    {
		parent::__construct();
    }
	
    public function handle()//
    {
		
		$app = new Sprintstatus($this->option());
		$app->Run();
    }
}