<?php

namespace App\Repositories;

use App\Models\UomGroup;
use App\Models\UomGroupUnit;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UomGroupRepository extends RepositoryBase
{
    protected $repositoryId = 'rinvex.repository.uom-groups';
    protected $model = UomGroup::class;

    public function getAdminListing(string $search = ''): Collection
    {
        return $this->uomGroupModel()
            ->newQuery()
            ->withCount('units')
            ->with('baseUnit')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('foreign_name', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get();
    }

    public function getOptions(?int $selectedGroupId = null): Collection
    {
        $groupKeyName = $this->uomGroupModel()->getKeyName();

        return $this->uomGroupModel()
            ->newQuery()
            ->with([
                'units' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                'units.unit',
            ])
            ->where(function ($query) use ($selectedGroupId, $groupKeyName) {
                $query->where('status', 'Active');

                if ($selectedGroupId) {
                    $query->orWhere($groupKeyName, $selectedGroupId);
                }
            })
            ->orderBy('name')
            ->get();
    }

    public function loadForAdminEdit(int $groupId): UomGroup
    {
        return $this->uomGroupModel()
            ->newQuery()
            ->with([
                'baseUnit',
                'units.unit',
                'units' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            ])
            ->whereKey($groupId)
            ->firstOrFail();
    }

    public function createForAdmin(array $attributes): UomGroup
    {
        $unitRows = $attributes['units'] ?? [];
        unset($attributes['units']);

        $group = DB::connection($this->uomGroupModel()->getConnectionName())->transaction(function () use ($attributes, $unitRows) {
            $group = $this->uomGroupModel();
            $group->fill($attributes);
            $group->save();

            $this->syncUnits($group, $unitRows);

            return $group;
        });

        UomGroup::flushQueryCache();
        UomGroupUnit::flushQueryCache();
        UnitOfMeasure::flushQueryCache();

        return $group;
    }

    public function updateForAdmin(UomGroup $group, array $attributes): UomGroup
    {
        $unitRows = $attributes['units'] ?? [];
        unset($attributes['units']);

        DB::connection($group->getConnectionName())->transaction(function () use ($group, $attributes, $unitRows) {
            $group->fill($attributes);
            $group->save();

            $this->syncUnits($group, $unitRows);
        });

        UomGroup::flushQueryCache();
        UomGroupUnit::flushQueryCache();
        UnitOfMeasure::flushQueryCache();

        return $group;
    }

    public function deleteForAdmin(UomGroup $group): void
    {
        $group->delete();
        UomGroup::flushQueryCache();
        UomGroupUnit::flushQueryCache();
        UnitOfMeasure::flushQueryCache();
    }

    protected function uomGroupModel(): UomGroup
    {
        /** @var \App\Models\UomGroup $model */
        $model = $this->createModel();

        if (tenant()) {
            $model->setConnection(tenant()->database_connection_name);
        }

        return $model;
    }

    protected function syncUnits(UomGroup $group, array $unitRows): void
    {
        $postedIds = collect($unitRows)
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        if (! empty($postedIds)) {
            $this->groupUnitModel()
                ->newQuery()
                ->where('uom_group_id', $group->id)
                ->whereNotIn('id', $postedIds)
                ->delete();
        } elseif ($group->exists) {
            $this->groupUnitModel()
                ->newQuery()
                ->where('uom_group_id', $group->id)
                ->delete();
        }

        $group->forceFill(['base_unit_id' => null])->save();

        foreach ($unitRows as $unitRow) {
            if ((bool) ($unitRow['_delete'] ?? false)) {
                if (! empty($unitRow['id'])) {
                    $this->groupUnitModel()
                        ->newQuery()
                        ->where('uom_group_id', $group->id)
                        ->whereKey((int) $unitRow['id'])
                        ->delete();
                }

                continue;
            }

            unset($unitRow['_delete']);

            $unitRow['uom_group_id'] = $group->id;
            $unitRow['unit_of_measure_id'] = (int) ($unitRow['unit_of_measure_id'] ?? 0);
            $unitRow['conversion_factor_to_base'] = $this->calculateConversionFactor($unitRow);

            if ($unitRow['is_base_unit']) {
                $unitRow['alternate_quantity'] = 1;
                $unitRow['base_quantity'] = 1;
                $unitRow['conversion_factor_to_base'] = 1;
            }

            if ($unitRow['is_base_unit']) {
                $group->forceFill(['base_unit_id' => $unitRow['unit_of_measure_id']])->save();
            }

            $groupUnit = null;

            if (! empty($unitRow['id'])) {
                $groupUnit = $this->groupUnitModel()
                    ->newQuery()
                    ->where('uom_group_id', $group->id)
                    ->whereKey((int) $unitRow['id'])
                    ->first();
            }

            $groupUnit ??= $this->groupUnitModel();
            $groupUnit->fill($unitRow);
            $groupUnit->save();
        }
    }

    protected function calculateConversionFactor(array $unitRow): float
    {
        $alternateQuantity = (float) ($unitRow['alternate_quantity'] ?? 1);
        $baseQuantity = (float) ($unitRow['base_quantity'] ?? 1);

        if ($alternateQuantity <= 0 || $baseQuantity <= 0) {
            return 1;
        }

        return round($baseQuantity / $alternateQuantity, 6);
    }

    protected function unitModel(): UnitOfMeasure
    {
        $model = new UnitOfMeasure();

        if (tenant()) {
            $model->setConnection(tenant()->database_connection_name);
        }

        return $model;
    }

    protected function groupUnitModel(): UomGroupUnit
    {
        $model = new UomGroupUnit();

        if (tenant()) {
            $model->setConnection(tenant()->database_connection_name);
        }

        return $model;
    }
}
