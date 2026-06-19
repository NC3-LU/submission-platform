<?php

namespace App\Notifications;

use App\Models\Form;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alerts a form owner when a submitted file is quarantined as malicious or
 * could not be scanned after exhausting all retries.
 */
class SubmissionFileScanAlert extends Notification
{
    use Queueable;

    /**
     * @param  string  $reason  Either 'quarantined' (malicious file removed) or
     *                          'scan_failed' (could not scan after retries).
     */
    public function __construct(
        public Form $form,
        public string $filename,
        public string $reason,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $title = $this->form->title ?? 'your form';

        if ($this->reason === 'quarantined') {
            return (new MailMessage)
                ->subject('Malicious file blocked on "'.$title.'": '.$this->filename)
                ->line('A file submitted to your form "'.$title.'" was identified as malicious and has been removed.')
                ->line('File name: '.$this->filename)
                ->line('The file was quarantined automatically and is no longer available for download. No action is required, but you may wish to review the related submission.');
        }

        return (new MailMessage)
            ->subject('File could not be scanned on "'.$title.'": '.$this->filename)
            ->line('A file submitted to your form "'.$title.'" could not be scanned after multiple attempts.')
            ->line('File name: '.$this->filename)
            ->line('As a precaution, the file is treated as untrusted and will not be served for download until it can be scanned successfully. Please review the related submission.');
    }
}
