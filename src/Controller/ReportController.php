<?php

namespace App\Controller;

use App\Repository\CostumeRepository;
use App\Repository\CustomOrderRepository;
use App\Repository\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/reports')]
final class ReportController extends AbstractController
{
    #[Route('/', name: 'app_report_index', methods: ['GET'])]
    public function index(CostumeRepository $costumeRepository, CustomOrderRepository $customOrderRepository, OrderRepository $orderRepository): Response
    {
        $totalSales = $customOrderRepository->getTotalSales();

        $bestSellingCustom = $customOrderRepository->getBestSellingCosplays();
        $bestSellingOrder = $orderRepository->getBestSellingCosplays();

        // Merge and sort best selling
        $bestSelling = array_merge($bestSellingCustom, $bestSellingOrder);
        usort($bestSelling, fn($a, $b) => $b['count'] <=> $a['count']);
        $bestSelling = array_slice($bestSelling, 0, 5);

        $outOfStock = $costumeRepository->findOutOfStock();

        return $this->render('report/index.html.twig', [
            'totalSales' => $totalSales,
            'bestSelling' => $bestSelling,
            'outOfStock' => $outOfStock,
        ]);
    }
}