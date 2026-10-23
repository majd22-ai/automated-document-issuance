<?php
namespace App\Mail;
use Illuminate\Mail\Mailable;
class CitizenUpdateMail extends Mailable
{
    public function build(): static { return $this->subject('تنبيه جديد في حسابك بالمنصة')->view('emails.citizen-update'); }
}
