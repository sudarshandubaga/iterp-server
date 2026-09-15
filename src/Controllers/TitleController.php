<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Request;
use Iterp\Models\Title;

/**
 * CRUD API for titles (Master Settings > Title).
 */
class TitleController extends CrudController
{
    protected string $model = Title::class;

    protected array $rules = [
        'name'       => 'required|string|max:255',
        'short_name' => 'required|string|max:50',
        'gender'     => 'required|in:male,female,both',
        'user_type'  => 'required|in:student,employee,both',
    ];

    protected string $orderBy = 'name';

    protected array $searchable = ['name', 'short_name', 'gender', 'user_type'];

    /**
     * Support fetching titles by `?gender=` and/or `?user_type=`. A value of
     * "both" is always implicitly included so a title configured as spanning
     * genders / user types is returned for any matching selection.
     */
    protected function applyFilters(Request $request, \Iterp\Core\QueryBuilder $query): void
    {
        $gender = $request->query('gender');
        if ($gender === 'm') {
            $gender = 'male';
        } elseif ($gender === 'f') {
            $gender = 'female';
        }
        if ($gender === 'male' || $gender === 'female') {
            $query->whereIn('gender', [$gender, Title::GENDER_BOTH]);
        }

        $userType = $request->query('user_type');
        if ($userType === 'student' || $userType === 'employee') {
            $query->whereIn('user_type', [$userType, Title::TYPE_BOTH]);
        }
    }
}