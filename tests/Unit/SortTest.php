<?php

namespace Okipa\LaravelTable\Tests\Unit;

use ErrorException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Okipa\LaravelTable\Table;
use Okipa\LaravelTable\Test\LaravelTableTestCase;
use Okipa\LaravelTable\Test\Models\Company;
use Okipa\LaravelTable\Test\Models\User;

class SortTest extends LaravelTableTestCase
{
    public function testNoSortableAttribute()
    {
        $users = $this->createMultipleUsers(2);
        $this->routes(['users'], ['index']);
        $table = (new Table)->model(User::class)->routes(['index' => ['name' => 'users.index']]);
        $table->column('name');
        $table->column('email');
        $table->configure();
        foreach ($users as $key => $user) {
            $this->assertEquals($user->name, $table->getPaginator()->items()[$key]['name']);
        }
    }

    public function testSetSortableAttribute()
    {
        $table = (new Table)->model(User::class);
        $table->column('name')->sortable();
        $this->assertTrue($table->getColumns()->first()->getIsSortable());
        $this->assertEquals(1, $table->getSortableColumns()->count());
        $this->assertEquals('name', $table->getSortableColumns()->first()->getDbField());
    }

    public function testSetSortByDefaultAttribute()
    {
        $this->routes(['users'], ['index']);
        $table = (new Table)->model(User::class)->routes(['index' => ['name' => 'users.index']]);
        $table->column('name');
        $table->column('email')->sortable(true, 'desc');
        $this->assertEquals('email', $table->getSortByValue());
        $this->assertEquals('desc', $table->getSortDirValue());
        $table->configure();
        $this->assertEquals('email', $table->getSortByValue());
        $this->assertEquals('desc', $table->getSortDirValue());
    }

    public function testSortByDefault()
    {
        $users = $this->createMultipleUsers(5);
        $this->routes(['users'], ['index']);
        $table = (new Table)->routes(['index' => ['name' => 'users.index']])->model(User::class);
        $table->column('name')->title('Name');
        $table->column('email')->title('Email')->sortable(true);
        $table->configure();
        $this->assertEquals(
            $users->sortBy('email')->values()->toArray(),
            $table->getPaginator()->toArray()['data']
        );
    }

    public function testSortByDefaultCalledMultiple()
    {
        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('The table is already sorted by the « name » database column. You only can sort '
            . 'a table column by default once');
        $table = (new Table)->model(User::class);
        $table->column('name')->sortable(true);
        $table->column('email')->sortable(true);
    }

    public function testNoSortableColumnDefined()
    {
        $this->createMultipleUsers(5);
        $this->routes(['users'], ['index']);
        $table = (new Table)->routes(['index' => ['name' => 'users.index']])->model(User::class);
        $table->column('name');
        $table->column('email');
        $table->configure();
        $this->assertNull($table->getSortByValue());
        $this->assertEquals($table->getSortDirValue(), 'asc');
    }

    public function testSortableColumnDefinedWithNoDefaultSort()
    {
        $this->createMultipleUsers(5);
        $this->routes(['users'], ['index']);
        $table = (new Table)->routes(['index' => ['name' => 'users.index']])->model(User::class);
        $table->column('name')->sortable();
        $table->column('email')->sortable();
        $table->configure();
        $this->assertEquals($table->getSortByValue(), $table->getColumns()->first()->getDbField());
        $this->assertEquals($table->getSortDirValue(), 'asc');
    }

    public function testSortByColumnWithoutAttribute()
    {
        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('One of the sortable table columns has no defined database column. '
            . 'You have to define a database column for each sortable table columns by '
            . 'setting a string parameter in the « column » method.');
        $this->createMultipleUsers(5);
        $this->routes(['companies'], ['index']);
        $table = (new Table)->routes(['index' => ['name' => 'companies.index']])->model(User::class);
        $table->column()->sortable();
        $table->configure();
    }

    public function testSortByColumn()
    {
        $users = $this->createMultipleUsers(3);
        $customRequest = (new Request)->merge([
            (new Table)->getRowsNumberField() => 20,
            (new Table)->getSortByField() => 'email',
            (new Table)->getSortDirField() => 'desc',
        ]);
        $this->routes(['users'], ['index']);
        $table = (new Table)->model(User::class)
            ->routes(['index' => ['name' => 'users.index']])
            ->request($customRequest);
        $table->column('name')->title('Name')->sortable();
        $table->column('email')->title('Email')->sortable();
        $table->configure();
        $this->assertEquals('email', $table->getSortByValue());
        $this->assertEquals('desc', $table->getSortDirValue());
        $this->assertEquals(
            $users->sortByDesc('email')->values()->toArray(),
            $table->getPaginator()->toArray()['data']
        );
    }

    public function testSortOnOtherTableFieldWithoutCustomTableDeclaration()
    {
        $this->createMultipleUsers(5);
        $companies = $this->createMultipleCompanies(5);
        $this->routes(['companies'], ['index']);
        $customRequest = (new Request)->merge([
            (new Table)->getRowsNumberField() => 20,
            (new Table)->getSortByField() => 'owner',
            (new Table)->getSortDirField() => 'desc',
        ]);
        $table = (new Table)->model(Company::class)
            ->routes(['index' => ['name' => 'companies.index']])
            ->query(function (Builder $query) {
                $query->select('companies_test.*');
                $query->addSelect('users_test.name as owner');
                $query->join('users_test', 'users_test.id', '=', 'companies_test.owner_id');
            })
            ->request($customRequest);
        $table->column('owner')->sortable();
        $table->configure();
        foreach ($companies->load('owner')->sortByDesc('owner.name')->values() as $key => $company) {
            $this->assertEquals($company->owner->name, $table->getPaginator()->toArray()['data'][$key]['owner']);
        }
    }

    public function testPaginateSortOnOtherTableField()
    {
        $this->createMultipleUsers(5);
        $this->createMultipleCompanies(10);
        $this->routes(['companies'], ['index']);
        $customRequest = (new Request)->merge([
            (new Table)->getRowsNumberField() => 5,
            (new Table)->getSortByField() => 'owner',
            (new Table)->getSortDirField() => 'desc',
        ]);
        $table = (new Table)->model(Company::class)
            ->routes(['index' => ['name' => 'companies.index']])
            ->query(function (Builder $query) {
                $query->select('companies_test.*');
                $query->addSelect('users_test.name as owner');
                $query->join('users_test', 'users_test.id', '=', 'companies_test.owner_id');
            })
            ->request($customRequest);
        $table->column('owner')->sortable();
        $table->configure();
        $paginatedCompanies = Company::join('users_test', 'users_test.id', '=', 'companies_test.owner_id')
            ->orderBy('users_test.name', 'desc')
            ->select('companies_test.*')
            ->with('owner')
            ->paginate(5);
        foreach ($paginatedCompanies as $key => $company) {
            $this->assertEquals($company->owner->name, $table->getPaginator()->toArray()['data'][$key]['owner']);
        }
    }

    public function testSortableColumnHtml()
    {
        $this->routes(['users'], ['index']);
        $table = (new Table)->model(User::class)->routes(['index' => ['name' => 'users.index']]);
        $table->column('name')->title('Name')->sortable();
        $table->column('email')->title('Email');
        $table->configure();
        $html = view('laravel-table::' . $table->getTheadTemplatePath(), compact('table'))->toHtml();
        $this->assertStringContainsString(
            'href="http://localhost/users/index?' . $table->getRowsNumberField() . '=20&amp;'
            . $table->getSortByField() . '=name&amp;'
            . $table->getSortDirField() . '=desc"',
            $html
        );
        $this->assertStringNotContainsString(
            'href="http://localhost/users/index?' . $table->getRowsNumberField() . '=20&amp;'
            . $table->getSortByField() . '=email&amp;'
            . $table->getSortDirField() . '=desc"',
            $html
        );
    }
}
