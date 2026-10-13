<?php
namespace JanKavach\Controllers;

use JanKavach\Core\Auth;
use JanKavach\Core\Controller;
use JanKavach\Core\Response;
use JanKavach\Core\Scope;
use JanKavach\Models\Application;
use JanKavach\Models\Dashboard;
use JanKavach\Models\Followup;
use JanKavach\Models\Geography;
use JanKavach\Models\Master;

/**
 * Dashboards and their chart feeds (specification sections 4, 5, 39).
 */
class DashboardController extends Controller
{
    public function index(): void
    {
        $filters = $this->dashboardFilters();

        $dashboard   = new Dashboard();
        $application = new Application();
        $followup    = new Followup();
        $geography   = new Geography();
        $master      = new Master();

        $kpis = $dashboard->kpis($filters);

        $this->view('dashboard/index', [
            'pageTitle'   => 'Dashboard',
            'breadcrumbs' => [['label' => 'Dashboard']],
            'filters'     => $filters,
            'kpis'        => $kpis,
            'funnel'      => $application->funnel($filters),
            'followupDue' => [
                'overdue'  => $followup->dueList('overdue', 6, $filters),
                'today'    => $followup->dueList('today', 6, $filters),
                'upcoming' => $followup->dueList('upcoming', 6, $filters),
            ],
            'recentBeneficiaries' => $dashboard->recentActivity(8),
            'topSchemes'          => $dashboard->schemeCoverage($filters, 8),
            'bottlenecks'         => $dashboard->bottlenecks($filters),
            'topFieldUsers'       => $dashboard->topFieldUsers(6),
            'stateCoverage'       => Auth::dataScope() === 'all' ? $dashboard->stateCoverage(12) : [],
            'districtCoverage'    => Auth::dataScope() !== 'all' ? $dashboard->districtCoverage($filters, 15) : [],

            // Filter dropdowns
            'states'            => $geography->stateOptions(),
            'districts'         => $geography->districtOptions($filters['state_id'] ?? null),
            'themes'            => $master->optionsFor('scheme_themes'),
            'beneficiaryTypes'  => $master->optionsFor('beneficiary_types'),
            'statuses'          => $master->optionsFor('application_statuses', 'name', 'stage_order'),
            'schemes'           => $this->schemeOptions($filters),
        ]);
    }

    /**
     * Chart data feed. Kept separate from the page so the dashboard renders
     * immediately and the charts fill in over AJAX.
     */
    public function charts(): void
    {
        $filters = $this->dashboardFilters();
        $dashboard = new Dashboard();
        $application = new Application();

        $palette = [
            '#0f4c81', '#2a9d8f', '#e9c46a', '#f4a261', '#e76f51', '#8ecae6',
            '#219ebc', '#023047', '#ffb703', '#fb8500', '#6a4c93', '#c9184a',
        ];

        $this->json([
            'success' => true,
            'palette' => $palette,
            'charts'  => [
                'scheme_level'        => $this->series($dashboard->schemesByLevel($filters)),
                'scheme_theme'        => $this->series($dashboard->schemesByTheme($filters, 10)),
                'scheme_beneficiary'  => $this->series($dashboard->schemesByBeneficiaryType($filters, 10)),
                'scheme_state'        => $this->series($dashboard->schemesByState(12)),
                'scheme_priority'     => $this->series($dashboard->schemesByPriority($filters)),
                'scheme_approach'     => $this->series($dashboard->schemesByServiceApproach($filters)),

                'beneficiary_state'    => $this->series($dashboard->beneficiariesByState($filters, 12)),
                'beneficiary_district' => $this->series($dashboard->beneficiariesByDistrict($filters, 12)),
                'beneficiary_gender'   => $this->series($dashboard->beneficiariesByGender($filters)),
                'beneficiary_age'      => $this->series($dashboard->beneficiariesByAgeGroup($filters)),
                'beneficiary_area'     => $this->series($dashboard->beneficiariesByArea($filters)),
                'beneficiary_category' => $this->series($dashboard->beneficiariesByCategory($filters)),

                'eligibility'          => $this->series($dashboard->eligibilityDistribution($filters)),
                'application_status'   => $this->series($dashboard->applicationsByStatus($filters)),
                'schemes_matched'      => $this->series($dashboard->schemesMatched($filters, 10)),

                'registration_trend'   => $this->series($dashboard->registrationTrend($filters, 12)),
                'application_trend'    => $this->trendSeries($dashboard->applicationTrend($filters, 12)),

                'funnel'               => array_values(array_filter(
                    $application->funnel($filters), fn($s) => empty($s['aside']))),
                'coverage'             => $dashboard->schemeCoverage($filters, 8),
            ],
        ]);
    }

    /** State-level drill-down. */
    public function state(string $id): void
    {
        $stateId = (int)$id;
        Scope::assertAccess($stateId, null);

        $geography = new Geography();
        $state = $this->findOrFail(
            $this->db->selectOne('SELECT * FROM states WHERE id = :id', ['id' => $stateId]),
            'State'
        );

        $filters = array_merge($this->dashboardFilters(), ['state_id' => $stateId, 'district_id' => null]);
        $dashboard = new Dashboard();

        $this->view('dashboard/state', [
            'pageTitle'   => $state['name'] . ' Dashboard',
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => '/dashboard'], ['label' => $state['name']]],
            'state'       => $state,
            'filters'     => $filters,
            'kpis'        => $dashboard->kpis($filters),
            'districtCoverage' => $dashboard->districtCoverage($filters, 50),
            'funnel'      => (new Application())->funnel($filters),
            'states'      => $geography->stateOptions(),
        ]);
    }

    /** District-level drill-down. */
    public function district(string $id): void
    {
        $districtId = (int)$id;
        $district = $this->findOrFail(
            $this->db->selectOne(
                'SELECT d.*, s.name AS state_name FROM districts d
                   JOIN states s ON s.id = d.state_id WHERE d.id = :id',
                ['id' => $districtId]
            ), 'District'
        );
        Scope::assertAccess((int)$district['state_id'], $districtId);

        $filters = array_merge($this->dashboardFilters(), [
            'state_id'    => (int)$district['state_id'],
            'district_id' => $districtId,
        ]);
        $dashboard = new Dashboard();
        $followup  = new Followup();

        // A district-scoped user may not open the State roll-up, so the
        // breadcrumb must not offer a link that would only be refused.
        $stateReachable = in_array(Auth::dataScope(), ['all', 'state'], true);
        $stateCrumb = ['label' => $district['state_name']];
        if ($stateReachable) {
            $stateCrumb['url'] = '/dashboard/state/' . (int)$district['state_id'];
        }

        $this->view('dashboard/district', [
            'pageTitle'   => $district['name'] . ' Dashboard',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => '/dashboard'],
                $stateCrumb,
                ['label' => $district['name']],
            ],
            'stateReachable' => $stateReachable,
            'district'    => $district,
            'filters'     => $filters,
            'kpis'        => $dashboard->kpis($filters),
            'funnel'      => (new Application())->funnel($filters),
            'topSchemes'  => $dashboard->schemeCoverage($filters, 10),
            'followupDue' => [
                'overdue'  => $followup->dueList('overdue', 10, $filters),
                'today'    => $followup->dueList('today', 10, $filters),
                'upcoming' => $followup->dueList('upcoming', 10, $filters),
            ],
            'recentBeneficiaries' => $dashboard->recentActivity(10),
        ]);
    }

    // =================================================================
    // Helpers
    // =================================================================

    /** Dashboard filters, already clamped to the user's own geography. */
    private function dashboardFilters(): array
    {
        $filters = $this->filters();

        // With no explicit date range, look at the whole dataset rather than
        // silently hiding older records.
        $filters['date_from'] = $filters['date_from'] ?: null;
        $filters['date_to']   = $filters['date_to'] ?: null;

        return $filters;
    }

    /** Scheme dropdown, limited to schemes the user can actually see. */
    private function schemeOptions(array $filters): array
    {
        [$scopeSql, $bindings] = Scope::schemeClause('s');
        $where = [$scopeSql, 's.deleted_at IS NULL', 's.is_active = 1'];

        if (!empty($filters['state_id'])) {
            $where[] = "(s.state_id = :o_state OR s.scheme_level = 'National')";
            $bindings['o_state'] = (int)$filters['state_id'];
        }

        return $this->db->pluck(
            'SELECT s.id, COALESCE(s.short_name, s.name) FROM schemes s
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY s.name LIMIT 400',
            $bindings
        );
    }

    /** Normalise a label/value result set for Chart.js. */
    private function series(array $rows): array
    {
        return [
            'labels' => array_map(fn($r) => (string)($r['label'] ?? ''), $rows),
            'values' => array_map(fn($r) => (float)($r['value'] ?? 0), $rows),
            'colors' => array_map(fn($r) => $r['color'] ?? null, $rows),
            'extra'  => $rows,
        ];
    }

    /** Multi-series trend for the application chart. */
    private function trendSeries(array $rows): array
    {
        return [
            'labels'    => array_map(fn($r) => (string)($r['label'] ?? ''), $rows),
            'created'   => array_map(fn($r) => (int)($r['value'] ?? 0), $rows),
            'submitted' => array_map(fn($r) => (int)($r['submitted'] ?? 0), $rows),
            'benefited' => array_map(fn($r) => (int)($r['benefited'] ?? 0), $rows),
        ];
    }
}
