<?php
// app/Mail/TicketPaidMail.php
namespace App\Mail;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class TicketPaidMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking, public string $pdfPath) {}

    public function build()
    {
        return $this->subject('Ваш квиток — ' . $this->booking->ticket_serial)
            ->view('mail.ticket_paid')
            ->attach(Storage::path($this->pdfPath), [
                'as' => $this->booking->ticket_serial . '.pdf',
                'mime' => 'application/pdf',
            ]);
    }
}
