<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendEmail extends Mailable
{
    use Queueable, SerializesModels;
    public $msg;
    public $sub;
    public $link;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($msg,$sub,$link)
    {
        $this->msg = $msg;
        $this->sub = $sub;
        $this->link = $link;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
            return $this->subject($this->sub)->view('emails.registration');

    }
}
