<?php
namespace JanKavach\Controllers\Api;

use JanKavach\Core\Controller;
use JanKavach\Core\Scope;
use JanKavach\Models\Application;
use JanKavach\Models\Dashboard;

/**
 * Dashboard aggregates over the API.
 */
class ApiDashboardController extends Controller
{
    public function kpis(): void
    {
        $filters = $this->apiFilters();
        $dashboard = new Dashboard();

        $this->jsonSuccess([
            'scope'   => Scope::label(),
            'filters' => $filters,
            'kpis'    => $dashboard->kpis($filters),
            'funnel'  => (new Application())->funnel($filters),
        ]);
    }

    public function charts(): void
    {
        $filters = $this->apiFilters();
        $dashboard = new Dashboard();

        $this->jsonSuccess([
            'schemes' => [
                'by_level'            => $dashboard->schemesByLevel($filters),
                'by_theme'            => $dashboard->schemesByTheme($filters, 12),
                'by_beneficiary_type' => $dashboard->schemesByBeneficiaryType($filters, 12),
                'by_priority'         => $dashboard->schemesByPriority($filters),
                'by_service_approach' => $dashboard->schemesByServiceApproach($filters),
            ],
            'beneficiaries' => [
                'by_state'    => $dashboard->beneficiariesByState($filters, 15),
                'by_district' => $dashboard->beneficiariesByDistrict($filters, 15),
                'by_gender'   => $dashboard->beneficiariesByGender($filters),
                'by_age'      => $dashboard->beneficiariesByAgeGroup($filters),
                'by_area'     => $dashboard->beneficiariesByArea($filters),
                'by_category' => $dashboard->beneficiariesByCategory($filters),
                'trend'       => $dashboard->registrationTrend($filters, 12),
            ],
            'applications' => [
                'by_status'  => $dashboard->applicationsByStatus($filters),
                'trend'      => $dashboard->applicationTrend($filters, 12),
                'bottlenecks'=> $dashboard->bottlenecks($filters),
            ],
            'coverage'    => $dashboard->schemeCoverage($filters, 12),
            'eligibility' => $dashboard->eligibilityDistribution($filters),
        ]);
    }

    private function apiFilters(): array
    {
        $stateId    = $this->request->int('state_id');
        $districtId = $this->request->int('district_id');
        [$stateId, $districtId] = Scope::constrainFilters($stateId, $districtId);

        return [
            'state_id'    => $stateId,
            'district_id' => $districtId,
            'scheme_id'   => $this->request->int('scheme_id'),
            'theme_id'    => $this->request->int('theme_id'),
            'date_from'   => $this->dateOrNull('date_from'),
            'date_to'     => $this->dateOrNull('date_to'),
        ];
    }
}
