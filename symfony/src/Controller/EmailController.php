<?php

namespace App\Controller;

use DateTimeImmutable;
use DateTimeZone;
use DirectoryIterator;
use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EmailController extends AbstractController
{
    #[Route('/emails/view/{acronym}/{id}', name: 'app_email_view', methods: ['GET'])]
    public function view(string $acronym, string $id): Response
    {
        if (!file_exists("/messages/{$acronym}/{$id}.json")) {
            Throw new NotFoundHttpException();
        }

        $message = json_decode(file_get_contents("/messages/{$acronym}/{$id}.json"), true);

        return $this->render('email/view.html.twig', [
            'subject' => $message['subject'],
            'message' => $message['body'],
        ]);
    }

    /**
     * @param string $acronym
     * @return array
     */
    private function getItemsByAcronym(string $acronym): array
    {
        if (!is_dir("/messages/{$acronym}")) {
            Throw new NotFoundHttpException();
        }

        $tz = new DateTimeZone('America/New_York');
        $items = [];
        $dir = new DirectoryIterator("/messages/{$acronym}/");
        foreach ($dir as $fileinfo) {
            /** @var SplFileInfo $fileinfo */
            if ($fileinfo->isDot()) {
                continue;
            }

            $message = json_decode(file_get_contents($fileinfo->getRealPath()), true);
            $date = DateTimeImmutable::createFromFormat('U', $message['udate'], $tz);

            $crawler = new Crawler($message['body']);
            $description = $crawler->filter('.editableBlock')->last()->text();
            $items[$message['udate']] = [
                'subject' => $message['subject'],
                'description' => $description,
                'date' => $date->format('D, d M Y H:i:s O'),
                'link' => $this->generateUrl('app_email_view', [
                    'acronym' => $acronym,
                    'id' => pathinfo($fileinfo->getPathname(), PATHINFO_FILENAME)
                ], UrlGeneratorInterface::ABSOLUTE_URL),
            ];
        }

        return $items;
    }

    #[Route('/emails/feed/{acronym}.xml', name: 'app_email_feed', methods: ['GET'])]
    public function feed(string $acronym): Response
    {
        $items = $this->getItemsByAcronym($acronym);
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml;charset=UTF-8');

        return $this->render('email/feed.xml.twig', [
            'acronym' => $acronym,
            'items' => $items,
        ], $response);
    }

    #[Route('/emails/list/{acronym}.html', name: 'app_email_list', methods: ['GET'])]
    public function list(string $acronym): Response
    {
        $items = $this->getItemsByAcronym($acronym);

        return $this->render('email/list.html.twig', [
            'acronym' => $acronym,
            'items' => $items,
        ]);
    }
}
