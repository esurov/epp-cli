<?php

namespace App\Console\Commands\Epp;

use App\Concerns\InteractsWithEpp;
use Illuminate\Console\Command;
use Metaregistrar\EPP\atEppPollRequest;

use function Laravel\Prompts\confirm;

class PollMessageCommand extends Command
{
    use InteractsWithEpp;

    protected $signature = 'epp:poll-message
        {--delete-after-poll : Delete the message after polling}
        {--cltrid= : Client transaction ID (4-64 chars)}
        {--logdir= : Directory for EPP log files}';

    protected $description = 'Poll for server messages';

    public function handle(): int
    {
        $deleteAfterPoll = $this->option('delete-after-poll');

        if (! $deleteAfterPoll && ! $this->option('no-interaction')) {
            $deleteAfterPoll = confirm('Delete messages after polling?', false);
        }

        return $this->executeEppOperation(function ($connection) use ($deleteAfterPoll) {
            $request = new atEppPollRequest(atEppPollRequest::POLL_REQ);
            $this->applyCltrid($request, $this->option('cltrid'));

            $response = $connection->request($request);

            $messageCount = $response->getMessageCount();

            $this->line('SUCCESS: ');
            $this->line("Messages waiting: $messageCount");

            if ($messageCount > 0) {
                $this->newLine();

                $msgId = $response->getMessageId();
                $this->line("message id: $msgId");

                $date = $this->formatDate($response->getMessageDate());
                $this->line("Queue-Date: $date");

                $this->line(sprintf('message desc: %s', $response->getDesc()));
                $this->line(sprintf('message type: %s', $response->getType()));
                $this->printXml($response);

                if ($deleteAfterPoll) {
                    $ackRequest = new atEppPollRequest(atEppPollRequest::POLL_ACK, $msgId);
                    $this->applyCltrid($ackRequest, $this->option('cltrid'));

                    $ackResponse = $connection->request($ackRequest);

                    if ($ackResponse->Success()) {
                        $this->newLine();
                        $this->line("Message $msgId deleted");
                    }

                    $response = $ackResponse;
                }
            }

            $this->newLine();
            $this->printTransactionIds($response);
        });
    }

    private function printXml(\DOMNode $node, string $prefix = ''): void
    {
        foreach ($node->childNodes as $child) {
            if ($child->nodeName === '#text') {
                continue;
            }

            $name = $child->nodeName;
            if ($child->hasAttributes() && in_array($name, ['condition', 'result'])) {
                foreach ($child->attributes as $attr) {
                    $this->line("$name {$attr->name}: {$attr->value}");
                }
            }

            if ($child->hasChildNodes()) {
                $this->printXml($child, "$name ");
                if (preg_match("/\n/", $child->textContent)) {
                    continue;
                }
            }

            if (in_array($name, ['msg', 'details', 'clTRID', 'svTRID'])) {
                $this->line("{$prefix}{$name}: {$child->textContent}");
            }
        }
    }
}
