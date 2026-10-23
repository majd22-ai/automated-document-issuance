<?php
namespace Tests\Integration;
use App\Models\User;
use App\Notifications\CitizenActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;
class NotificationsConcurrencyTest extends TestCase
{
    private ?User $citizen=null;
    protected function setUp(): void
    {
        parent::setUp();
        if(DB::getDriverName()!=='mysql' || !str_ends_with(DB::connection()->getDatabaseName(),'_testing')) { $this->markTestSkipped('Explicit MySQL testing DB required'); }
        $this->artisan('migrate',['--force'=>true])->assertSuccessful();
        $this->citizen=User::factory()->create(['username'=>'NOTIFY-RACE-'.Str::uuid()]);
    }
    protected function tearDown(): void
    {
        if($this->citizen) { $this->citizen->documents()->delete(); DB::table('users')->where('id',$this->citizen->id)->delete(); }
        parent::tearDown();
    }
    private function race(string $mode,string $id='none'): array
    {
        $release=(string)(microtime(true)+2); $processes=[];
        for($i=0;$i<2;$i++) { $process=new Process([PHP_BINARY,base_path('tests/Support/notification-worker.php'),$mode,$id,$release],base_path()); $process->setTimeout(40); $process->start(); $processes[]=$process; }
        $counts=[]; foreach($processes as $process) { $process->wait(); $this->assertTrue($process->isSuccessful(),$process->getErrorOutput()); $counts[]=json_decode($process->getOutput(),true,16,JSON_THROW_ON_ERROR)['count']; }
        sort($counts); return $counts;
    }
    public function test_concurrent_expiry_commands_create_one_period_notification(): void
    {
        $this->citizen->documents()->create(['document_type'=>'TEST','document_number'=>'EXPIRY-RACE-'.Str::uuid(),'status'=>'active','issued_at'=>today()->subYear(),'expires_at'=>today()->addDays(7)]);
        $this->assertSame([0,1],$this->race('expiry'));
        $this->assertSame(1,$this->citizen->notifications()->count());
    }
    public function test_concurrent_mail_workers_send_only_one_fake_message(): void
    {
        config(['citizen_notifications.mail_enabled'=>true,'mail.default'=>'smtp','queue.default'=>'sync']);
        $this->citizen->notifyNow(new CitizenActivity('TEST mail race',['title'=>'TEST title','body'=>'TEST body'],email:true));
        $notification=$this->citizen->notifications()->firstOrFail();
        $this->assertSame([0,1],$this->race('mail',$notification->id));
        $this->assertSame('sent',$notification->fresh()->mail_status); $this->assertSame(1,$notification->fresh()->mail_attempts);
    }
    public function test_dispatch_failure_after_commit_preserves_business_change_and_pending_inbox(): void
    {
        $mail=\Mockery::mock(\App\Services\Notifications\NotificationMail::class)->makePartial();
        $mail->shouldReceive('configured')->andReturn(true); $mail->shouldReceive('enqueue')->once()->andThrow(new \RuntimeException('TEST queue unavailable'));
        $this->app->instance(\App\Services\Notifications\NotificationMail::class,$mail);
        DB::transaction(function() {
            $this->citizen->forceFill(['phone'=>'TEST committed change'])->save();
            $this->citizen->notifyNow(new CitizenActivity('TEST aftercommit',['title'=>'TEST title','body'=>'TEST body'],email:true));
        });
        $this->assertSame('TEST committed change',$this->citizen->fresh()->phone);
        $this->assertSame('pending',$this->citizen->notifications()->firstOrFail()->mail_status);
    }
}
