<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\SyncRun;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the administrators when the automatic sync of a project stops with a failure. */
class SyncRunFailed extends Notification
{
    public function __construct(private SyncRun $run, private Project $project)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('The sync of "' . $this->project->name . '" failed')
            ->line('The sync of the project "' . $this->project->name . '" with the collection database stopped with a failure:')
            ->line($this->run->message)
            ->line('Nothing was lost: the values that were waiting are still waiting, and the next scheduled run will try again. You will not get another message until a run works and fails again.')
            ->action('See the log of the run', url('/sync-runs/' . $this->run->id));
    }
}
