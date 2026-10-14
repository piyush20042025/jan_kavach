<?php
namespace JanKavach\Core;

/**
 * Base controller.  Gives every controller the request, a view() helper that
 * injects the shared layout data, plus validation / JSON / redirect shortcuts.
 */
abstract class Controller
{
    protected Request $request;
    protected Database $db;

    public function __construct(?Request $request = null)
    {
        $this->request = $request ?? new Request();
        $this->db      = Database::getInstance();
    }

    // ------------------------------------------------------------------
    // Rendering
    // ------------------------------------------------------------------

    /** Render a view inside the application layout. */
    protected function view(string $view, array $data = [], ?string $layout = 'layouts/app'): void
    {
        $data = array_merge($this->layoutData(), $data);
        View::display($view, $data, $layout);
    }

    /** Render without the admin chrome (login, print views). */
    protected function bare(string $view, array $data = [], ?string $layout = 'layouts/blank'): void
    {
        $data = array_merge($this->layoutData(), $data);
        View::display($view, $data, $layout);
    }

    /** Data every page needs: current user, flash messages, nav counters. */
    protected function layoutData(): array
    {
        return [
            'authUser'      => Auth::user(),
            'flashMessages' => Flash::pull(),
            'errors'        => Flash::errors(),
            'old'           => Flash::oldInput(),
            'appName'       => Config::get('app.name'),
            'appTagline'    => Config::get('app.tagline'),
            'appVersion'    => Config::get('app.version'),
            'scopeLabel'    => Auth::check() ? Scope::label() : '',
            'currentPath'   => $this->request->path(),
        ];
    }

    // ------------------------------------------------------------------
    // Responses
    // ------------------------------------------------------------------

    protected function json($data, int $status = 200): void
    {
        Response::json($data, $status);
    }

    protected function jsonSuccess($data = null, string $message = 'OK'): void
    {
        Response::success($data, $message);
    }

    protected function jsonError(string $message, $errors = null, int $status = 400): void
    {
        Response::error($message, $errors, $status);
    }

    protected function redirect(string $path, int $status = 302): void
    {
        Response::to($path, $status);
    }

    protected function back(string $fallback = '/dashboard'): void
    {
        Response::back($fallback);
    }

    /** Redirect with a success flash. */
    protected function redirectWithSuccess(string $path, string $message): void
    {
        Flash::success($message);
        $this->redirect($path);
    }

    protected function redirectWithError(string $path, string $message): void
    {
        Flash::error($message);
        $this->redirect($path);
    }

    /**
     * Bounce back to the form with the errors and the submitted values so
     * the user does not have to retype anything.
     */
    protected function backWithErrors(array $errors, string $fallback = '/dashboard'): void
    {
        Flash::setErrors($errors);
        Flash::setOldInput($this->request->all());
        Flash::error('Please correct the highlighted fields and try again.');
        $this->back($fallback);
    }

    // ------------------------------------------------------------------
    // Validation
    // ------------------------------------------------------------------

    /**
     * Validate the request. On failure: answers JSON for AJAX, otherwise
     * redirects back with errors. Returns the validated data on success.
     */
    protected function validate(array $rules, array $labels = [], string $fallback = '/dashboard'): array
    {
        $validator = Validator::make($this->request->all(), $rules, $labels);

        if ($validator->fails()) {
            if ($this->request->isAjax()) {
                $this->jsonError('Validation failed. Please check the form.', $validator->firstErrors(), 422);
            }
            $this->backWithErrors($validator->firstErrors(), $fallback);
        }
        return $validator->validated();
    }

    /** Validate without the automatic redirect, for custom handling. */
    protected function validator(array $rules, array $labels = []): Validator
    {
        return Validator::make($this->request->all(), $rules, $labels);
    }

    // ------------------------------------------------------------------
    // Authorisation shortcuts
    // ------------------------------------------------------------------

    protected function authorize(string $permission, string $message = ''): void
    {
        Auth::authorize($permission, $message);
    }

    protected function can(string $permission): bool
    {
        return Auth::can($permission);
    }

    /** 404 unless the row exists. */
    protected function findOrFail(?array $row, string $what = 'Record'): array
    {
        if (!$row) {
            if ($this->request->isAjax()) {
                $this->jsonError($what . ' not found.', null, 404);
            }
            Response::abort(404, $what . ' not found.');
        }
        return $row;
    }

    // ------------------------------------------------------------------
    // Common request parsing
    // ------------------------------------------------------------------

    /**
     * Read the standard dashboard/report filters, clamped to what the
     * signed-in user is allowed to see.
     */
    protected function filters(array $extra = []): array
    {
        $stateId    = $this->request->int('state_id');
        $districtId = $this->request->int('district_id');
        [$stateId, $districtId] = Scope::constrainFilters($stateId, $districtId);

        $filters = [
            'date_from'         => $this->dateOrNull('date_from'),
            'date_to'           => $this->dateOrNull('date_to'),
            'state_id'          => $stateId,
            'district_id'       => $districtId,
            'block_id'          => $this->request->int('block_id'),
            'scheme_id'         => $this->request->int('scheme_id'),
            'theme_id'          => $this->request->int('theme_id'),
            'scheme_level'      => $this->inOrNull('scheme_level', ['National', 'State', 'National+State']),
            'beneficiary_type_id' => $this->request->int('beneficiary_type_id'),
            'status_id'         => $this->request->int('status_id'),
            'gender'            => $this->inOrNull('gender', ['Male', 'Female', 'Other']),
            'area_type'         => $this->inOrNull('area_type', ['Rural', 'Urban']),
            'field_user_id'     => $this->request->int('field_user_id'),
            'search'            => $this->request->filled('search') ? (string)$this->request->input('search') : null,
        ];

        foreach ($extra as $key => $default) {
            $filters[$key] = $this->request->filled($key) ? $this->request->input($key) : $default;
        }
        return $filters;
    }

    /** A date filter, normalised to Y-m-d, or null when absent/invalid. */
    protected function dateOrNull(string $key): ?string
    {
        if (!$this->request->filled($key)) {
            return null;
        }
        $value = (string)$this->request->input($key);
        $time  = strtotime($value);
        return $time === false ? null : date('Y-m-d', $time);
    }

    /** Only accept a value from a whitelist - never trust request enums. */
    protected function inOrNull(string $key, array $allowed): ?string
    {
        $value = $this->request->input($key);
        return ($value !== null && $value !== '' && in_array((string)$value, $allowed, true)) ? (string)$value : null;
    }

    /** Resolve a sort column against a whitelist map. */
    protected function sort(array $allowed, string $default, string $defaultDir = 'ASC'): array
    {
        $key = (string)$this->request->input('sort', $default);
        $dir = strtoupper((string)$this->request->input('dir', $defaultDir)) === 'DESC' ? 'DESC' : 'ASC';

        $column = $allowed[$key] ?? $allowed[$default] ?? null;
        if ($column === null) {
            throw new \RuntimeException('No default sort column configured.');
        }
        return [$column, $dir, $key];
    }
}
