<?php
namespace JanKavach\Core;

/**
 * Base controller for the application.
 *
 * Most controllers only need a typed Request and a few convenience wrappers
 * around the JSON and view helpers in the core layer.
 */
class Controller
{
    protected Request $request;
    protected Database $db;

    public function __construct(?Request $request = null)
    {
        $this->request = $request ?? new Request();
        $this->db      = Database::getInstance();
    }

    /**
     * Render and display a view inside a layout.
     */
    protected function view(string $template, array $data = [], ?string $layout = 'layouts/app'): void
    {
        View::display($template, $data, $layout);
    }

    /**
     * Render and display a bare view without standard admin layout (or with a custom layout).
     */
    protected function bare(string $template, array $data = [], ?string $layout = null): void
    {
        View::display($template, $data, $layout);
    }

    protected function display(string $template, array $data = [], ?string $layout = 'layouts/app'): void
    {
        View::display($template, $data, $layout);
    }

    /**
     * Standard filters extracted from query params and scoped to the user.
     */
    protected function filters(): array
    {
        $stateId    = $this->request->int('state_id');
        $districtId = $this->request->int('district_id');
        [$stateId, $districtId] = Scope::constrainFilters($stateId, $districtId);

        return [
            'search'            => $this->request->filled('search') ? (string)$this->request->input('search') : null,
            'state_id'          => $stateId,
            'district_id'       => $districtId,
            'block_id'          => $this->request->int('block_id'),
            'gram_panchayat_id' => $this->request->int('gram_panchayat_id'),
            'village_id'        => $this->request->int('village_id'),
            'field_user_id'     => $this->request->int('field_user_id'),
            'scheme_id'         => $this->request->int('scheme_id'),
            'theme_id'          => $this->request->int('theme_id'),
            'gender'            => $this->inOrNull('gender', ['Male', 'Female', 'Other']),
            'area_type'         => $this->inOrNull('area_type', ['Rural', 'Urban']),
            'date_from'         => $this->dateOrNull('date_from'),
            'date_to'           => $this->dateOrNull('date_to'),
        ];
    }

    /**
     * Sort resolution against an allowed column map.
     */
    protected function sort(array $allowed, string $defaultKey, string $defaultDir = 'ASC'): array
    {
        $sortKey = (string)$this->request->input('sort', $defaultKey);
        $dir     = strtoupper((string)$this->request->input('dir', $defaultDir));
        if (!in_array($dir, ['ASC', 'DESC'], true)) {
            $dir = $defaultDir;
        }
        $column = $allowed[$sortKey] ?? ($allowed[$defaultKey] ?? reset($allowed));
        return [$column, $dir, $sortKey];
    }

    protected function inOrNull(string $key, array $allowed): ?string
    {
        $val = $this->request->input($key);
        return ($val !== null && in_array((string)$val, $allowed, true)) ? (string)$val : null;
    }

    protected function dateOrNull(string $key): ?string
    {
        $val = trim((string)$this->request->input($key, ''));
        if ($val === '') {
            return null;
        }
        $d = \DateTime::createFromFormat('Y-m-d', $val);
        return ($d && $d->format('Y-m-d') === $val) ? $val : null;
    }

    protected function findOrFail(?array $entity, string $label = 'Record'): array
    {
        if (!$entity) {
            Response::abort(404, "{$label} not found.");
        }
        return $entity;
    }

    protected function backWithErrors(array $errors, string $fallback = '/'): void
    {
        Flash::setValidationErrors($errors);
        Flash::setOldInput($this->request->all());
        $this->back($fallback);
    }

    protected function jsonSuccess($data = null, string $message = 'OK', int $status = 200): void
    {
        Response::success($data, $message, $status);
    }

    protected function jsonError(string $message = 'Request failed', $errors = null, int $status = 400): void
    {
        Response::error($message, $errors, $status);
    }

    protected function redirect(string $url, int $status = 302): void
    {
        Response::redirect($url, $status);
    }

    protected function back(string $fallback = '/'): void
    {
        Response::back($fallback);
    }

    protected function abort(int $status, string $message = ''): void
    {
        Response::abort($status, $message);
    }
}
