<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $manager;

    protected User $sales;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->manager = User::factory()->create([
            'role' => UserRole::Manager,
        ]);

        $this->sales = User::factory()->create([
            'role' => UserRole::Sales,
        ]);
    }

    /**
     * Test guests are redirected to login when visiting the reports hub.
     */
    public function test_guest_is_redirected_to_login_from_reports_hub(): void
    {
        $response = $this->get(route('reports.index'));
        $response->assertRedirect(route('login'));
    }

    /**
     * Test sales role is forbidden from accessing the reports hub.
     */
    public function test_sales_role_is_forbidden_from_reports_hub(): void
    {
        $response = $this->actingAs($this->sales)->get(route('reports.index'));
        $response->assertForbidden();
    }

    /**
     * Test admin and manager roles can access the reports hub.
     */
    public function test_admin_and_manager_can_access_reports_hub(): void
    {
        $adminResponse = $this->actingAs($this->admin)->get(route('reports.index'));
        $adminResponse->assertOk();
        $adminResponse->assertSee('Reports Hub');

        $managerResponse = $this->actingAs($this->manager)->get(route('reports.index'));
        $managerResponse->assertOk();
        $managerResponse->assertSee('Reports Hub');
    }

    /**
     * Test reports hub renders responsive summary cards for admin with Sales Report card visible.
     */
    public function test_reports_hub_renders_all_three_cards_for_admin(): void
    {
        $response = $this->actingAs($this->admin)->get(route('reports.index'));

        $response->assertOk();
        // Summary cards
        $response->assertSee('Sales Reports');
        $response->assertSee(route('reports.sales'));
        $response->assertSee('Adjustments Report');
        $response->assertSee(route('reports.adjustments'));
        $response->assertSee('Vendor Aging Report');
        $response->assertSee(route('reports.vendor-aging'));
        $response->assertSee('Admin Only');
    }

    /**
     * Test reports hub hides Sales Report card for manager.
     */
    public function test_reports_hub_hides_sales_report_card_for_manager(): void
    {
        $response = $this->actingAs($this->manager)->get(route('reports.index'));

        $response->assertOk();
        // Manager should see Adjustments and Vendor Aging cards
        $response->assertSee('Adjustments Report');
        $response->assertSee(route('reports.adjustments'));
        $response->assertSee('Vendor Aging Report');
        $response->assertSee(route('reports.vendor-aging'));

        // Manager must not see the Sales Report card or link
        $response->assertDontSee(route('reports.sales'));
        $response->assertDontSee('View Sales Report');
    }

    /**
     * Test dedicated Reports navigation dropdown renders expected links for admin and manager.
     */
    public function test_reports_navigation_dropdown_renders_expected_links(): void
    {
        // Admin navigation check
        $adminResponse = $this->actingAs($this->admin)->get(route('dashboard'));
        $adminResponse->assertOk();
        $adminResponse->assertSee(route('reports.index'));
        $adminResponse->assertSee('All Reports Overview');
        $adminResponse->assertSee(route('reports.sales'));
        $adminResponse->assertSee(route('reports.adjustments'));
        $adminResponse->assertSee(route('reports.vendor-aging'));

        // Manager navigation check
        $managerResponse = $this->actingAs($this->manager)->get(route('dashboard'));
        $managerResponse->assertOk();
        $managerResponse->assertSee(route('reports.index'));
        $managerResponse->assertSee('All Reports Overview');
        $managerResponse->assertSee(route('reports.adjustments'));
        $managerResponse->assertSee(route('reports.vendor-aging'));
        // Manager navigation must not have Sales Reports link
        $managerResponse->assertDontSee(route('reports.sales'));

        // Sales user navigation must not have Reports dropdown at all
        $salesResponse = $this->actingAs($this->sales)->get(route('dashboard'));
        $salesResponse->assertOk();
        $salesResponse->assertDontSee(route('reports.index'));
        $salesResponse->assertDontSee('All Reports Overview');
    }

    /**
     * Test report links are absent from Sales, Buying, Stock, and Admin dropdowns (desktop and mobile).
     */
    public function test_navigation_dropdowns_do_not_contain_report_links(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertOk();
        $content = $response->getContent();

        // 1. Desktop Sales Dropdown
        $salesDropdown = $this->extractSection($content, '<!-- Sales Dropdown -->', '<!-- Buying Dropdown -->');
        $this->assertStringNotContainsString(route('reports.sales'), $salesDropdown);
        $this->assertStringNotContainsString('reports.sales', $salesDropdown);

        // 2. Desktop Buying Dropdown
        $buyingDropdown = $this->extractSection($content, '<!-- Buying Dropdown -->', '<!-- Stock Dropdown -->');
        $this->assertStringNotContainsString(route('reports.vendor-aging'), $buyingDropdown);
        $this->assertStringNotContainsString('Vendor Aging Report', $buyingDropdown);

        // 3. Desktop Stock Dropdown
        $stockDropdown = $this->extractSection($content, '<!-- Stock Dropdown -->', '<!-- Finance Dropdown -->');
        $this->assertStringNotContainsString(route('reports.adjustments'), $stockDropdown);
        $this->assertStringNotContainsString('Adjustments Report', $stockDropdown);

        // 4. Desktop Admin Dropdown
        $adminDropdown = $this->extractSection($content, '<!-- Admin Dropdown -->', '<!-- User Auth Buttons -->');
        $this->assertStringNotContainsString(route('reports.sales'), $adminDropdown);
        $this->assertStringNotContainsString(route('reports.adjustments'), $adminDropdown);
        $this->assertStringNotContainsString(route('reports.vendor-aging'), $adminDropdown);
        $this->assertStringNotContainsString('Sales Reports', $adminDropdown);
        $this->assertStringNotContainsString('Adjustments Report', $adminDropdown);
        $this->assertStringNotContainsString('Vendor Aging Report', $adminDropdown);

        // 5. Mobile Sales Accordion
        $mobileSales = $this->extractSection($content, '<!-- Mobile Sales Accordion -->', '<!-- Mobile Buying Accordion -->');
        $this->assertStringNotContainsString(route('reports.sales'), $mobileSales);

        // 6. Mobile Buying Accordion
        $mobileBuying = $this->extractSection($content, '<!-- Mobile Buying Accordion -->', '<!-- Mobile Stock Accordion -->');
        $this->assertStringNotContainsString(route('reports.vendor-aging'), $mobileBuying);
        $this->assertStringNotContainsString('Vendor Aging Report', $mobileBuying);

        // 7. Mobile Stock Accordion
        $mobileStock = $this->extractSection($content, '<!-- Mobile Stock Accordion -->', '<!-- Mobile Finance Accordion -->');
        $this->assertStringNotContainsString(route('reports.adjustments'), $mobileStock);
        $this->assertStringNotContainsString('Adjustments Report', $mobileStock);

        // 8. Mobile Admin Accordion
        $mobileAdmin = $this->extractSection($content, '<!-- Mobile Admin Accordion -->', '<!-- Mobile User Menu -->');
        $this->assertStringNotContainsString(route('reports.sales'), $mobileAdmin);
        $this->assertStringNotContainsString(route('reports.adjustments'), $mobileAdmin);
        $this->assertStringNotContainsString(route('reports.vendor-aging'), $mobileAdmin);
    }

    /**
     * Test active route conditions in navigation view do not match reports for Buying or Admin.
     */
    public function test_buying_and_admin_nav_conditions_exclude_reports(): void
    {
        $navigationViewContent = file_get_contents(resource_path('views/layouts/navigation.blade.php'));

        // Buying dropdown active conditions must not mention reports
        $buyingSection = $this->extractSection($navigationViewContent, '<!-- Buying Dropdown -->', '<!-- Stock Dropdown -->');
        $this->assertStringNotContainsString('reports.', $buyingSection);

        $mobileBuyingSection = $this->extractSection($navigationViewContent, '<!-- Mobile Buying Accordion -->', '<!-- Mobile Stock Accordion -->');
        $this->assertStringNotContainsString('reports.', $mobileBuyingSection);

        // Admin dropdown active conditions must not mention reports
        $adminSection = $this->extractSection($navigationViewContent, '<!-- Admin Dropdown -->', '<!-- User Auth Buttons -->');
        $this->assertStringNotContainsString('reports.', $adminSection);

        $mobileAdminSection = $this->extractSection($navigationViewContent, '<!-- Mobile Admin Accordion -->', '<!-- Mobile User Menu -->');
        $this->assertStringNotContainsString('reports.', $mobileAdminSection);
    }

    /**
     * Test that visiting report pages marks Reports nav active and not Buying or Admin.
     */
    public function test_visiting_report_routes_activates_reports_nav_only(): void
    {
        // When visiting vendor-aging report
        $response = $this->actingAs($this->admin)->get(route('reports.vendor-aging'));
        $response->assertOk();
        $content = $response->getContent();

        // Check desktop Reports dropdown has active class (rounded-md bg-muted px-4)
        $reportsDropdown = $this->extractSection($content, '<!-- Reports Dropdown -->', '<!-- Admin Dropdown -->');
        $this->assertStringContainsString('rounded-md bg-muted px-4', $reportsDropdown);

        // Check desktop Buying dropdown is inactive (rounded-md bg-background px-4)
        $buyingDropdown = $this->extractSection($content, '<!-- Buying Dropdown -->', '<!-- Stock Dropdown -->');
        $this->assertStringContainsString('rounded-md bg-background px-4', $buyingDropdown);
        $this->assertStringNotContainsString('rounded-md bg-muted px-4', $buyingDropdown);

        // Check mobile Buying Accordion is collapsed (expanded: false)
        $mobileBuying = $this->extractSection($content, '<!-- Mobile Buying Accordion -->', '<!-- Mobile Stock Accordion -->');
        $this->assertStringContainsString('expanded: false', $mobileBuying);

        // Check mobile Reports Accordion is expanded (expanded: true)
        $mobileReports = $this->extractSection($content, '<!-- Mobile Reports Accordion -->', '<!-- Mobile Admin Accordion -->');
        $this->assertStringContainsString('expanded: true', $mobileReports);

        // When visiting sales report, admin dropdown is inactive
        $salesResponse = $this->actingAs($this->admin)->get(route('reports.sales'));
        $salesResponse->assertOk();
        $salesContent = $salesResponse->getContent();

        $adminDropdown = $this->extractSection($salesContent, '<!-- Admin Dropdown -->', '<!-- User Auth Buttons -->');
        $this->assertStringContainsString('rounded-md bg-background px-4', $adminDropdown);
        $this->assertStringNotContainsString('rounded-md bg-muted px-4', $adminDropdown);

        // Mobile Admin Accordion is collapsed
        $mobileAdmin = $this->extractSection($salesContent, '<!-- Mobile Admin Accordion -->', '<!-- Mobile User Menu -->');
        $this->assertStringContainsString('expanded: false', $mobileAdmin);
    }

    /**
     * Extract substring between two marker strings.
     */
    private function extractSection(string $content, string $startMarker, string $endMarker): string
    {
        $startPos = strpos($content, $startMarker);
        if ($startPos === false) {
            $this->fail("Start marker [{$startMarker}] not found in content.");
        }

        $endPos = strpos($content, $endMarker, $startPos);
        if ($endPos === false) {
            $this->fail("End marker [{$endMarker}] not found in content after start marker.");
        }

        return substr($content, $startPos, $endPos - $startPos);
    }
}
