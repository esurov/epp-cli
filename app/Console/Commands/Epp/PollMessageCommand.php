<?php

namespace App\Console\Commands\Epp;

use App\EppCommand;
use Metaregistrar\EPP\atEppPollRequest;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\confirm;

class PollMessageCommand extends EppCommand
{
    protected function configure(): void
    {
        $this
            ->setName('message:poll')
            ->setDescription('Poll for server messages')
            ->addOption('delete-after-poll', null, InputOption::VALUE_NONE, 'Delete the message after polling')
            ->addOption('cltrid', null, InputOption::VALUE_REQUIRED, 'Client transaction ID (4-64 chars)')
            ->addOption('logdir', null, InputOption::VALUE_REQUIRED, 'Directory for EPP log files');
    }

    protected function handle(): int
    {
        $deleteAfterPoll = $this->option('delete-after-poll');
        $interactiveDelete = false;

        if (! $deleteAfterPoll && ! $this->input->getOption('no-interaction')) {
            $deleteAfterPoll = confirm('Delete messages after polling?', false);
            $interactiveDelete = true;
        }
        $this->trackOption('delete-after-poll', $deleteAfterPoll, $interactiveDelete);

        $this->printCliEquivalent();

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
