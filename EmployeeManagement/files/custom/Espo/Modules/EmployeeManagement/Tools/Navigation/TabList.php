<?php
declare(strict_types=1);
namespace Espo\Modules\EmployeeManagement\Tools\Navigation;

/** Cleanup for sidebar entries previously inserted automatically by this extension. */
final class TabList
{
    public const SCOPES = ['EmployeeManagement', 'EmployeeAttendance', 'EmployeeAttendanceOverview', 'EmployeeAdministration'];

    public static function remove(array $tabs): array
    {
        $result = [];
        foreach ($tabs as $item) {
            if (is_string($item)) {
                if (!in_array($item, self::SCOPES, true)) { $result[] = $item; }
                continue;
            }
            $group = (array) $item;
            if (isset($group['itemList'])) {
                $children = self::remove($group['itemList']);
                if (!$children) { continue; }
                $group['itemList'] = $children;
                $item = is_object($item) ? (object) $group : $group;
            }
            $result[] = $item;
        }
        return array_values($result);
    }
}
