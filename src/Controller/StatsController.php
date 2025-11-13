<?php

namespace App\Controller;

use App\Repository\TimeBookingRepository;
use DateInterval;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/stats', name: 'api_stats_')]
#[IsGranted('ROLE_USER')]
class StatsController extends AbstractController
{
    public function __construct(private readonly TimeBookingRepository $timeBookings)
    {
    }

    #[Route('', name: 'overview', methods: ['GET'])]
    public function overview(Request $request): JsonResponse
    {
        $period = (string)($request->query->get('period') ?? 'current_month');
        [$start, $end] = $this->computeRange($period);

        $rows = $this->timeBookings->aggregateByProjectInRange($start, $end);
        $items = [];
        $totalMinutes = 0;
        $totalRevenue = 0.0;
        foreach ($rows as $r) {
            $minutes = (int)($r['minutes'] ?? 0);
            $hours = $minutes / 60.0;
            $rate = $r['hourlyRate'] !== null ? (float)$r['hourlyRate'] : 0.0;
            // Revenue only when rate is available (TM or fixed with derived rate)
            $revenue = $rate > 0 ? ($hours * $rate) : 0.0;
            $items[] = [
                'projectId' => (int)$r['projectId'],
                'projectName' => (string)$r['projectName'],
                'minutes' => $minutes,
                'hours' => round($hours, 2),
                'revenue' => round($revenue, 2),
                'budgetType' => (string)$r['budgetType'],
                'hourlyRate' => $r['hourlyRate'] !== null ? number_format((float)$r['hourlyRate'], 2, '.', '') : null,
            ];
            $totalMinutes += $minutes;
            $totalRevenue += $revenue;
        }

        return $this->json([
            'period' => $period,
            'start' => $start->format(DATE_ATOM),
            'end' => $end->format(DATE_ATOM),
            'items' => $items,
            'totals' => [
                'minutes' => $totalMinutes,
                'hours' => round($totalMinutes / 60.0, 2),
                'revenue' => round($totalRevenue, 2),
            ],
        ]);
    }

    /**
     * @return array{0:DateTimeImmutable,1:DateTimeImmutable}
     */
    private function computeRange(string $period): array
    {
        $now = new DateTimeImmutable('now');
        // Normalize to start of day
        $today = $now->setTime(0, 0, 0);

        $period = in_array($period, ['current_month','last_month','quarter','year','current_year'], true) ? $period : 'current_month';

        if ($period === 'last_month') {
            $firstThisMonth = $today->modify('first day of this month');
            $start = $firstThisMonth->modify('-1 month');
            $end = $firstThisMonth; // exclusive
            return [$start, $end];
        }
        if ($period === 'quarter') {
            // Determine current quarter start, then return start of that quarter .. start+3 months
            $month = (int)$today->format('n');
            $qStartMonth = (int)(floor(($month - 1) / 3) * 3) + 1; // 1,4,7,10
            $start = $today->setDate((int)$today->format('Y'), $qStartMonth, 1);
            $end = $start->add(new DateInterval('P3M'));
            return [$start, $end];
        }
        if ($period === 'year' || $period === 'current_year') {
            $start = $today->setDate((int)$today->format('Y'), 1, 1);
            $end = $start->add(new DateInterval('P1Y'));
            return [$start, $end];
        }
        // default current_month
        $start = $today->modify('first day of this month');
        $end = $start->add(new DateInterval('P1M'));
        return [$start, $end];
    }
}
