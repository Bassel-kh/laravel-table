<?php

namespace Okipa\LaravelTable\Tests\Unit;

use Illuminate\Support\Collection;
use Okipa\LaravelTable\Table;
use Okipa\LaravelTable\Test\LaravelTableTestCase;
use Okipa\LaravelTable\Test\Models\Company;
use Okipa\LaravelTable\Test\Models\User;

class ResultDeclarationTest extends LaravelTableTestCase
{
    public function testSetResultsAttribute()
    {
        $table = (new Table)->model(User::class);
        $table->result()->title('Test');
        $this->assertEquals($table->getResults()->count(), 1);
        $this->assertEquals($table->getResults()->first()->getTitle(), 'Test');
    }

    public function testResultRowsGivePaginatedRowsToManipulate()
    {
        $this->createMultipleUsers(10);
        $this->routes(['users'], ['index']);
        $table = (new Table)->model(User::class)
            ->routes(['index' => ['name' => 'users.index']])
            ->rowsNumber(5);
        $table->column('name');
        $table->result()->title('Test')->html(fn(Collection $paginatedRows) => $this->assertCount(5, $paginatedRows));
        $table->configure();
        view('laravel-table::' . $table->getTbodyTemplatePath(), compact('table'))->toHtml();
    }

    public function testSetResultsHtml()
    {
        $this->createMultipleUsers(10);
        $companies = $this->createMultipleCompanies(5);
        $this->routes(['users'], ['index']);
        $table = (new Table)->model(Company::class)->routes(['index' => ['name' => 'users.index']]);
        $table->column('name');
        $table->column('turnover');
        $table->result()->title('Result !')->html(fn(Collection $paginatedRows) => $paginatedRows->sum('turnover'));
        $table->configure();
        $html = view('laravel-table::' . $table->getTbodyTemplatePath(), compact('table'))->toHtml();
        $this->assertStringContainsString('Result !', $html);
        $this->assertStringContainsString((string) $companies->sum('turnover'), $html);
    }

    public function testSetResultsMultipleHtml()
    {
        $this->createMultipleUsers(10);
        $companies = $this->createMultipleCompanies(5);
        $this->routes(['users'], ['index']);
        $table = (new Table)->model(Company::class)
            ->routes(['index' => ['name' => 'users.index']])
            ->rowsNumber(2);
        $table->column('name');
        $table->column('turnover');
        $table->result()
            ->title('Selected turnover')
            ->html(fn(Collection $paginatedRows) => $paginatedRows->sum('turnover'));
        $table->result()->title('Total turnover')->html(fn() => (new Company)->all()->sum('turnover'));
        $table->configure();
        $html = view('laravel-table::' . $table->getTbodyTemplatePath(), compact('table'))->toHtml();
        $this->assertStringContainsString('Selected turnover', $html);
        $this->assertStringContainsString((string) $companies->sum('turnover'), $html);
        $this->assertStringContainsString('Total turnover', $html);
    }

    public function testSetNoResult()
    {
        $this->createMultipleUsers(10);
        $this->createMultipleCompanies(5);
        $this->routes(['users'], ['index']);
        $table = (new Table)->model(Company::class)
            ->routes(['index' => ['name' => 'users.index']])
            ->rowsNumber(2);
        $table->column('name');
        $table->column('turnover');
        $table->configure();
        $html = view('laravel-table::' . $table->getTbodyTemplatePath(), compact('table'))->toHtml();
        $this->assertStringNotContainsString('result', $html);
    }

    public function testResultColspanWithSingleColumn()
    {
        $this->createMultipleUsers(10);
        $this->createMultipleCompanies(5);
        $this->routes(['users'], ['index']);
        $table = (new Table)->model(Company::class)->routes(['index' => ['name' => 'users.index']]);
        $table->column('name');
        $table->result()
            ->title('Selected turnover')
            ->html(fn(Collection $paginatedRows) => $paginatedRows->sum('turnover'));
        $table->configure();
        $html = view('laravel-table::' . $table->getResultsTemplatePath(), compact('table'))->toHtml();
        $this->assertEquals(1, substr_count($html, '<td'));
        $this->assertStringNotContainsString('colspan', $html);
    }

    public function testResultColspanWithMultipleColumns()
    {
        $this->createMultipleUsers(10);
        $this->createMultipleCompanies(5);
        $this->routes(['users'], ['index']);
        $table = (new Table)->model(Company::class)->routes(['index' => ['name' => 'users.index']]);
        $table->column('name');
        $table->column('turnover');
        $table->result()
            ->title('Selected turnover')
            ->html(fn(Collection $paginatedRows) => $paginatedRows->sum('turnover'));
        $table->configure();
        $html = view('laravel-table::' . $table->getResultsTemplatePath(), compact('table'))->toHtml();
        $this->assertEquals(1, substr_count($html, '<td'));
        $this->assertStringContainsString('colspan="2"', $html);
    }

    public function testResultColspanTestWithEditRoute()
    {
        $this->createMultipleUsers(10);
        $this->createMultipleCompanies(5);
        $this->routes(['users'], ['index', 'edit']);
        $table = (new Table)->model(Company::class)->routes([
            'index' => ['name' => 'users.index'],
            'edit' => ['name' => 'users.edit'],
        ]);
        $table->column('owner_id');
        $table->column('name');
        $table->column('turnover');
        $table->result()
            ->title('Selected turnover')
            ->html(fn(Collection $paginatedRows) => $paginatedRows->sum('turnover'));
        $table->configure();
        $html = view('laravel-table::' . $table->getResultsTemplatePath(), compact('table'))->toHtml();
        $this->assertEquals(1, substr_count($html, '<td'));
        $this->assertStringContainsString('colspan="4"', $html);
    }
}
