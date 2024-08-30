<?php

namespace App\Controller;

use App\IkonnAuth;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DefaultController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(): Response
    {
        $query = '{"query": "query MyQuery { schools(limit: 100) { items { acronym name level } } }" }';
        $response = HttpClient::create()
            ->request('POST', 'https://api.hocoschools.org/graphql', [
                'headers' => ['Authorization' => 'Bearer ' . (new IkonnAuth())->getToken()->getToken()],
                'body' => $query,
            ]);

        $schools = json_decode($response->getContent(), true)['data']['schools']['items'];
        usort($schools, function ($a, $b) { return $a['name'] <=> $b['name']; });
        $levels = array_reduce($schools, function ($carry, $item) {
            $level = match ($item['level']) {
                'es' => 'Elementary Schools',
                'ms' => 'Middle Schools',
                'hs' => 'High Schools',
                'ec' => 'Education Centers',
            };
            $carry[$level][] = $item;
            return $carry;
        });
        uksort($levels, function ($a, $b) {
            $integerize = function ($level) {
                return match ($level) {
                    'Elementary Schools' => 0,
                    'Middle Schools' => 1,
                    'High Schools' => 2,
                    'Education Centers' => 3,
                };
            };

            $a_level = $integerize($a);
            $b_level = $integerize($b);

            return $a_level <=> $b_level;
        });

        return $this->render('default/index.html.twig', [
            'levels' => $levels,
        ]);
    }
}
