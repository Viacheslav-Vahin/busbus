<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Booking;


class TicketPdfMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $record, public string $pdfBinary) {}

    public function build()
    {
        return $this->subject('Ваш квиток ' . $this->record->ticket_serial)
            ->html('<p>Доброго дня! Надсилаємо ваш квиток у вкладенні. Гарної поїздки!</p>')
            ->attachData(
                $this->pdfBinary,
                'ticket_' . $this->record->ticket_serial . '.pdf',
                ['mime' => 'application/pdf']
            );
    }
}

//class TicketPdfMail extends Mailable
//{
//    use Queueable, SerializesModels;
//
//    /**
//     * Create a new message instance.
//     */
//    public function __construct()
//    {
//        //
//    }
//
//    /**
//     * Get the message envelope.
//     */
//    public function envelope(): Envelope
//    {
//        return new Envelope(
//            subject: 'Ticket Pdf Mail',
//        );
//    }
//
//    /**
//     * Get the message content definition.
//     */
//    public function content(): Content
//    {
//        return new Content(
//            view: 'view.name',
//        );
//    }
//
//    /**
//     * Get the attachments for the message.
//     *
//     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
//     */
//    public function attachments(): array
//    {
//        return [];
//    }
//}
