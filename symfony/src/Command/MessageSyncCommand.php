<?php

namespace App\Command;

use App\IkonnAuth;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;

#[AsCommand(
    name: 'app:message:sync',
    description: 'Add a short description for your command',
)]
class MessageSyncCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'all',
            'a',
            InputOption::VALUE_NONE,
            'Sync all messages, not just those that are unread.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $all  = $input->getOption('all');

        $query = '{"query": "query MyQuery { schools(limit: 100) { items { acronym name } } }" }';
        $response = HttpClient::create()
            ->request('POST', 'https://api.hocoschools.org/graphql', [
                'headers' => ['Authorization' => 'Bearer ' . (new IkonnAuth())->getToken()->getToken()],
                'body' => $query,
            ]);

        $schools = json_decode($response->getContent(), true)['data']['schools']['items'];
        $school_map = [
            'Worthington ES' => 'wes',
            'Howard County Public School System' => 'hcpss',
        ];
        foreach ($schools as $school) {
            $school_map[$school['name']] = $school['acronym'];
        }

        foreach ($school_map as $name => $acronym) {
            if (!is_dir('/messages/' . $acronym)) {
                mkdir('/messages/' . $acronym);
            }
        }

        $mailbox = imap_open(
            '{imap.gmail.com:993/imap/ssl/novalidate-cert}INBOX',
            getenv('GMAIL_ADDRESS'),
            getenv('GMAIL_PASSWORD')
        );
        if ($mailbox === false) {
            $io->error(imap_last_error());
            return Command::FAILURE;
        }

        $status = imap_status($mailbox, '{imap.gmail.com:993/imap/ssl/novalidate-cert}INBOX', SA_ALL);
        if ($status === false) {
            $io->error(imap_last_error());
            return Command::FAILURE;
        }

        $messages = imap_search($mailbox, $all ? 'UNDELETED' : 'UNSEEN');
        if ($messages === false) {
            $io->warning('No new messages found.');
            return Command::SUCCESS;
        }

        foreach ($messages as $uid) {
            $message = imap_headerinfo($mailbox, $uid);
            if (!$message) {
                continue;
            }

            $from = $message->fromaddress;
            $from_parts = explode('<', $from);
            $school_name = trim(array_shift($from_parts), ' "');
            if (!array_key_exists($school_name, $school_map)) {
                $io->warning($school_name . ' is not a valid school');
                continue;
            }
            $acronym = $school_map[$school_name];

            $date = DateTimeImmutable::createFromFormat('U', $message->udate);
            $filename = $date->format('Y-m-d-H:i:s-') . trim($message->message_id, '<>') . '.json';
            $body = quoted_printable_decode(imap_fetchbody($mailbox, $uid, 1));
            $crawler = new Crawler($body);

            try {
                $crawler->filter('p[style="font-family:verdana; color:#6B6B6B; font-size:75%"]')->each(function (Crawler $p) {
                    foreach ($p as $node) {
                        $node->parentNode->removeChild($node);
                    }
                });
            } catch (\Exception $e) {
                echo "$acronym: $filename\n";
                $io->error($e->getMessage());
            }

            $message->body = $crawler->html();
            file_put_contents("/messages/$acronym/$filename", json_encode($message, JSON_PRETTY_PRINT));
            imap_setflag_full($mailbox, $uid, "\\Seen \\Flagged", ST_UID);
        }

        imap_close($mailbox);
        return Command::SUCCESS;
    }
}
