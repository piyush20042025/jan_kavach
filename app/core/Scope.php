<?php
namespace JanKavach\Core;

/**
 * Geographic data scoping.
 *
 * Section 25 of the specification requires that user access automatically
 * respects the Country > State > District hierarchy: a Karnataka State
 * Administrator sees all Karnataka data, a Bengaluru Urban District User
 * sees only Bengaluru Urban data.
 *
 * Rather than repeat that logic in every query, models ask Scope for a
 * WHERE fragment and bindings, keyed by the table alias holding the
 * state_id / district_id / field_user_id columns.  The fragment is built
 * from the signed-in user's role data_scope, never from request input.
 */
class Scope
{
    /**
     * Returns [sqlFragment, bindings] to AND into a query.
     *
     * @param string $alias      table alias, e.g. 'b' for beneficiaries
     * @param bool   $ownerScope whether the table has a field_user_id column
     *                           that the 'own' scope should filter on
     */
    public static function clause(string $alias, bool $ownerScope = true, string $ownerColumn = 'field_user_id'): array
    {
        $alias = self::safeAlias($alias);

        if (!Auth::check()) {
            // No session: deny everything rather than leak.
            return ['1 = 0', []];
        }

        switch (Auth::dataScope()) {
            case 'all':
                return ['1 = 1', []];

            case 'state':
                $stateId = Auth::stateId();
                if (!$stateId) {
                    // A state-scoped user with no state assigned sees nothing.
                    return ['1 = 0', []];
                }
                return ["{$alias}.state_id = :scope_state", ['scope_state' => $stateId]];

            case 'district':
                $districtId = Auth::districtId();
                $stateId    = Auth::stateId();
                if ($districtId) {
                    return ["{$alias}.district_id = :scope_district", ['scope_district' => $districtId]];
                }
                if ($stateId) {
                    // District user without a district falls back to state.
                    return ["{$alias}.state_id = :scope_state", ['scope_state' => $stateId]];
                }
                return ['1 = 0', []];

            case 'own':
            default:
                $userId = Auth::id();
                if ($ownerScope) {
                    // Field users see the records they collected, plus anything
                    // in their own district that is assigned to them.
                    return [
                        "({$alias}.{$ownerColumn} = :scope_user OR {$alias}.created_by = :scope_creator)",
                        ['scope_user' => $userId, 'scope_creator' => $userId],
                    ];
                }
                $districtId = Auth::districtId();
                if ($districtId) {
                    return ["{$alias}.district_id = :scope_district", ['scope_district' => $districtId]];
                }
                return ['1 = 0', []];
        }
    }

    /**
     * Scope for tables that carry no geography of their own but belong to a
     * beneficiary. Produces an EXISTS sub-query against `beneficiaries`.
     */
    public static function beneficiarySubquery(string $alias, string $column = 'beneficiary_id'): array
    {
        $alias = self::safeAlias($alias);
        [$inner, $bindings] = self::clause('scoped_b');

        $sql = "EXISTS (SELECT 1 FROM beneficiaries scoped_b
                         WHERE scoped_b.id = {$alias}.{$column}
                           AND scoped_b.deleted_at IS NULL
                           AND {$inner})";
        return [$sql, $bindings];
    }

    /**
     * Which schemes may this user see?  National schemes are visible to
     * everyone; State schemes only to users of that state (and to anyone
     * with the 'all' scope).
     */
    public static function schemeClause(string $alias = 's'): array
    {
        $alias = self::safeAlias($alias);

        if (!Auth::check()) {
            return ['1 = 0', []];
        }
        if (Auth::dataScope() === 'all') {
            return ['1 = 1', []];
        }

        $stateId = Auth::stateId();
        if (!$stateId) {
            // Users with no state see the national catalogue only.
            return ["{$alias}.scheme_level = 'National'", []];
        }

        // National, or explicitly mapped to the user's state (covers the
        // "National + <State>" rows in the source workbook).
        return [
            "({$alias}.scheme_level = 'National'
               OR {$alias}.state_id = :scope_scheme_state
               OR EXISTS (SELECT 1 FROM scheme_states sss
                           WHERE sss.scheme_id = {$alias}.id
                             AND sss.state_id = :scope_scheme_state2))",
            ['scope_scheme_state' => $stateId, 'scope_scheme_state2' => $stateId],
        ];
    }

    /**
     * Which users may this user manage / see in listings?
     * Super Admin sees all; State Admin sees their state; others see only
     * themselves.
     */
    public static function userClause(string $alias = 'u', string $idColumn = 'id'): array
    {
        $alias    = self::safeAlias($alias);
        // Reporting views expose the user key as user_id rather than id, so
        // the column the self-match uses has to be nameable by the caller.
        $idColumn = self::safeAlias($idColumn);

        if (!Auth::check()) {
            return ['1 = 0', []];
        }
        switch (Auth::dataScope()) {
            case 'all':
                return ['1 = 1', []];
            case 'state':
                $stateId = Auth::stateId();
                return $stateId
                    ? ["{$alias}.state_id = :scope_user_state", ['scope_user_state' => $stateId]]
                    : ['1 = 0', []];
            case 'district':
                $districtId = Auth::districtId();
                return $districtId
                    ? ["({$alias}.district_id = :scope_user_district OR {$alias}.{$idColumn} = :scope_self)",
                       ['scope_user_district' => $districtId, 'scope_self' => Auth::id()]]
                    : ["{$alias}.{$idColumn} = :scope_self", ['scope_self' => Auth::id()]];
            default:
                return ["{$alias}.{$idColumn} = :scope_self", ['scope_self' => Auth::id()]];
        }
    }

    /**
     * Force a state/district filter supplied by the user back inside their
     * own permitted range. Returns the values that are actually safe to use.
     */
    public static function constrainFilters(?int $stateId, ?int $districtId): array
    {
        switch (Auth::dataScope()) {
            case 'all':
                return [$stateId, $districtId];

            case 'state':
                $own = Auth::stateId();
                // Ignore any other state that was asked for.
                return [$own, self::districtBelongsToState($districtId, $own) ? $districtId : null];

            case 'district':
            case 'own':
            default:
                return [Auth::stateId(), Auth::districtId() ?: (self::districtBelongsToState($districtId, Auth::stateId()) ? $districtId : null)];
        }
    }

    private static function districtBelongsToState(?int $districtId, ?int $stateId): bool
    {
        if (!$districtId || !$stateId) {
            return false;
        }
        return (int)Database::getInstance()->scalar(
            'SELECT COUNT(*) FROM districts WHERE id = :d AND state_id = :s',
            ['d' => $districtId, 's' => $stateId]
        ) > 0;
    }

    /**
     * Guard a single record before showing/editing it. Aborts with 403 when
     * the record sits outside the user's geography.
     */
    public static function assertAccess(?int $stateId, ?int $districtId, ?int $ownerId = null): void
    {
        if (!Auth::check()) {
            Response::abort(401, 'Please sign in to continue.');
        }
        $scope = Auth::dataScope();

        if ($scope === 'all') {
            return;
        }
        if ($scope === 'state') {
            if ($stateId !== null && $stateId === Auth::stateId()) {
                return;
            }
            Response::abort(403, 'This record belongs to a different State.');
        }
        if ($scope === 'district') {
            if ($districtId !== null && $districtId === Auth::districtId()) {
                return;
            }
            // District user with no district falls back to state check.
            if (Auth::districtId() === null && $stateId !== null && $stateId === Auth::stateId()) {
                return;
            }
            Response::abort(403, 'This record belongs to a different District.');
        }
        // 'own' - the narrowest scope, and it has to stay narrower than
        // 'district', otherwise a field user could open records their own
        // listings deliberately exclude.
        if ($ownerId !== null) {
            if ($ownerId === Auth::id()) {
                return;
            }
            Response::abort(403, 'You can only access records you collected.');
        }
        // No owner to check against (a new record, or a district-level page):
        // fall back to the user's own district.
        if ($districtId !== null && $districtId === Auth::districtId()) {
            return;
        }
        Response::abort(403, 'You can only access records you collected.');
    }

    /** Human-readable description of the active scope, for the header. */
    public static function label(): string
    {
        $user = Auth::user();
        if (!$user) {
            return '';
        }
        return match (Auth::dataScope()) {
            'all'   => 'National',
            'state' => $user['state_name'] ?? 'State',
            default => trim(($user['district_name'] ?? '') . ($user['state_name'] ? ', ' . $user['state_name'] : '')) ?: 'Assigned area',
        };
    }

    /** Only [A-Za-z0-9_] may reach SQL as an identifier. */
    private static function safeAlias(string $alias): string
    {
        $clean = preg_replace('/[^A-Za-z0-9_]/', '', $alias);
        if ($clean === '') {
            throw new \InvalidArgumentException('Invalid table alias supplied to Scope.');
        }
        return $clean;
    }
}
