<?php
declare(strict_types=1);

namespace Prismic\Test;

use Prismic\Api;
use Prismic\Ref;
use Prismic\SearchForm;
use Prismic\Form;
use Prismic\ApiData;
use Prismic\Cache\CacheInterface;
use Prismic\Predicates;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class SearchFormTest extends TestCase
{
    use ProphecyTrait;

    /** @var ApiData */
    private $apiData;

    /** @var \GuzzleHttp\ClientInterface */
    private $httpClient;

    /** @var CacheInterface */
    private $cache;

    /** @var Form */
    private $form;

    /**
     * @see fixtures/data.json
     */
    private $expectedMasterRef = 'UgjWQN_mqa8HvPJY';

    public function setUp(): void
    {
        $this->apiData = ApiData::withJsonString($this->getJsonFixture('data.json'));
        $this->form = Form::withJsonObject($this->apiData->getForms()['blogs']);
        $this->httpClient = $this->prophesize(GuzzleClient::class);
        $this->cache = $this->prophesize(CacheInterface::class);
    }

    protected function getSearchForm() : SearchForm
    {
        return new SearchForm(
            $this->httpClient->reveal(),
            $this->cache->reveal(),
            $this->form,
            $this->form->defaultData()
        );
    }

    public function testGetDataReturnsArray()
    {
        $form = $this->getSearchForm();
        $this->assertIsArray($form->getData());
    }

    public function testSetWithAnEmptyKeyThrowsException()
    {
        $this->expectException(\Prismic\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Form parameter key must be a non-empty string');

        $form = $this->getSearchForm();
        $form->set('', 'foo');
    }

    public function testSetWithANonScalarValueThrowsException()
    {
        $this->expectException(\Prismic\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Form parameter value must be scalar');

        $form = $this->getSearchForm();
        $form->set('page', ['an-array']);
    }

    public function testSetWithAnUnknownKeyThrowsException()
    {
        $this->expectException(\Prismic\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown form field parameter');

        $form = $this->getSearchForm();
        $form->set('whatever', 'foo');
    }

    public function testSetStringParamWithNonStringThrowsException()
    {
        $this->expectException(\Prismic\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('expects a string parameter');

        $form = $this->getSearchForm();
        $form->set('lang', 1);
    }

    public function testSetIntParamWithNonNumberThrowsException()
    {
        $this->expectException(\Prismic\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('expects an integer parameter');

        $form = $this->getSearchForm();
        $form->set('page', 'foo');
    }

    protected function assertSearchFormClone(SearchForm $a, SearchForm $b)
    {
        $this->assertNotSame($a, $b);
    }

    public function testSetIsSuccessfulForSingleScalarValue()
    {
        $form = $this->getSearchForm();
        $data = $form->getData();
        $this->assertEquals('1', $data['page']);

        $clone = $form->set('page', 10);

        $this->assertSearchFormClone($form, $clone);

        $data = $clone->getData();
        $this->assertEquals('10', $data['page']);
    }

    public function testSetAppendsForMultipleFields()
    {
        $form = $this->getSearchForm();
        $data = $form->getData();
        $this->assertCount(1, $data['q']);
        $this->assertNotContains('some-value', $data['q']);
        $clone = $form->set('q', 'some-value');
        $data = $clone->getData();
        $this->assertCount(2, $data['q']);
        $this->assertContains('some-value', $data['q']);
    }

    public function testRefAcceptsString()
    {
        $form = $this->getSearchForm();
        $clone = $form->ref('some-ref');
        $this->assertSearchFormClone($form, $clone);
        $data = $clone->getData();
        $this->assertSame('some-ref', $data['ref']);
    }

    public function testRefAcceptsRef()
    {
        $ref = current($this->apiData->getRefs());
        $form = $this->getSearchForm();
        $clone = $form->ref($ref);
        $data = $clone->getData();
        $this->assertSame((string) $ref, $data['ref']);
    }

    private function assertScalarOptionIsSet(SearchForm $form, string $key, $expectedValue)
    {
        $data = $form->getData();
        $this->assertArrayHasKey($key, $data);
        $this->assertSame($expectedValue, $data[$key]);
    }

    private function assertScalarOptionIsNotSet(SearchForm $form, string $key)
    {
        $data = $form->getData();
        $this->assertArrayNotHasKey($key, $data);
    }

    public function testAfter()
    {
        $this->assertScalarOptionIsSet(
            $this->getSearchForm()->after('Whatever'),
            'after',
            'Whatever'
        );
    }

    public function testLang()
    {
        $this->assertScalarOptionIsSet(
            $this->getSearchForm()->lang('en-gb'),
            'lang',
            'en-gb'
        );
    }

    public function testPageSize()
    {
        $this->assertScalarOptionIsSet(
            $this->getSearchForm()->pageSize(99),
            'pageSize',
            99
        );
    }

    public function testPage()
    {
        $this->assertScalarOptionIsSet(
            $this->getSearchForm()->page(99),
            'page',
            99
        );
    }

    public function testFetchWithStringArgs()
    {
        $this->assertScalarOptionIsSet(
            $this->getSearchForm()->fetch('one', 'two', 'three'),
            'fetch',
            'one,two,three'
        );
    }

    public function testFetchWithArrayArg()
    {
        $this->assertScalarOptionIsSet(
            $this->getSearchForm()->fetch(...['one','two','three']),
            'fetch',
            'one,two,three'
        );
    }

    public function testFetchLinksWithStringArgs()
    {
        $this->assertScalarOptionIsSet(
            $this->getSearchForm()->fetchLinks('one', 'two', 'three'),
            'fetchLinks',
            'one,two,three'
        );
    }

    public function testGraphQueryWithStringArg()
    {
        $query = '{
            blogpost {
                title
            }
        }';

        $this->assertScalarOptionIsSet(
            $this->getSearchForm()->graphQuery($query),
            'graphQuery',
            $query
        );
    }

    public function testOrderingsWithStringArgs()
    {
        $this->assertScalarOptionIsSet(
            $this->getSearchForm()->orderings('one', 'two', 'three'),
            'orderings',
            '[one,two,three]'
        );
    }

    public function testOrderingsStripsSquareBrackets()
    {
        $this->assertScalarOptionIsSet(
            $this->getSearchForm()->orderings('[my.foo desc]', '[my.bar]'),
            'orderings',
            '[my.foo desc,my.bar]'
        );
    }

    public function testOrderingsWillAcceptUnpackedArrays()
    {
        $this->assertScalarOptionIsSet(
            $this->getSearchForm()->orderings(...['[my.a]', 'my.b', 'my.c desc']),
            'orderings',
            '[my.a,my.b,my.c desc]'
        );
    }

    public function testOrderingsFiltersEmptyValues()
    {
        $this->assertScalarOptionIsSet(
            $this->getSearchForm()->orderings(...['', 'my.b', '', 'my.c desc']),
            'orderings',
            '[my.b,my.c desc]'
        );
    }

    public function testOrderingsIsNotSetWhenOnlyEmptyValuesAreProvided()
    {
        $this->assertScalarOptionIsNotSet(
            $this->getSearchForm()->orderings(...['', '']),
            'orderings'
        );
    }

    public function testStringQueryIsUnprocessedInQuery()
    {
        $form = $this->getSearchForm()->query('[:d = at(document.id, "ValidIdentifier")]');
        $data = $form->getData();
        $this->assertArrayHasKey('q', $data);
        $this->assertContains('[:d = at(document.id, "ValidIdentifier")]', $data['q']);
    }

    public function testSinglePredicateArgumentInQuery()
    {
        $predicate = Predicates::at('document.id', 'SomeId');
        $expect = sprintf('[%s]', $predicate->q());
        $form = $this->getSearchForm()->query($predicate);
        $data = $form->getData();
        $this->assertContains($expect, $data['q']);
    }

    public function testMultiplePredicatesInQuery()
    {
        $predicateA = Predicates::at('document.id', 'SomeId');
        $predicateB = Predicates::any('document.tags', 'Some Tag');
        $expect = sprintf('[%s%s]', $predicateA->q(), $predicateB->q());
        $form = $this->getSearchForm()->query($predicateA, $predicateB);
        $data = $form->getData();
        $this->assertContains($expect, $data['q']);
    }

    public function testUnpackedPredicateArrayInQuery()
    {
        $query = [
            Predicates::at('document.id', 'SomeId'),
            Predicates::any('document.tags', 'Some Tag'),
        ];
        $expect = sprintf('[%s%s]', $query[0]->q(), $query[1]->q());
        $form = $this->getSearchForm()->query(...$query);
        $data = $form->getData();
        $this->assertContains($expect, $data['q']);
    }

    public function testRegularArrayArgumentInQuery()
    {
        $query = [
            Predicates::at('document.id', 'SomeId'),
            Predicates::any('document.tags', 'Some Tag'),
        ];
        $expect = sprintf('[%s%s]', $query[0]->q(), $query[1]->q());
        $form = $this->getSearchForm()->query($query);
        $data = $form->getData();
        $this->assertContains($expect, $data['q']);
    }

    public function testEmptyArgumentToQueryHasNoEffect()
    {
        $form = $this->getSearchForm()->query('');
        $data = $form->getData();
        $field = $this->form->getField('q');
        $this->assertCount(1, $data['q']);
        $this->assertContains($field->getDefaultValue(), $data['q']);
    }

    public function testUrlRemovesPhpArrayKeys()
    {
        $form = $this->getSearchForm()->query('query_string');
        $url = $form->url();
        $query = parse_url($url, PHP_URL_QUERY);
        $this->assertSame(2, substr_count($query, 'q='));
    }

    public function testCachedResponseWillBeReturnedInSubmit()
    {
        $cachedJson = \json_decode('{"some":"data"}');
        $this->cache->get(Argument::type('string'))->willReturn($cachedJson);
        $response = $this->getSearchForm()->submit();
        $this->assertSame($cachedJson, $response);
    }

    public function testExceptionIsThrownForInvalidForm()
    {
        $this->expectException(\Prismic\Exception\RuntimeException::class);
        $this->expectExceptionMessage('Form type not supported');

        $formJson = '{
            "method": "POST",
            "enctype": "application/x-www-form-urlencoded",
            "action": "https://whatever/api/v2/documents/search",
            "fields": {}
        }';
        $form = Form::withJsonString($formJson);
        $searchForm = new SearchForm(
            $this->httpClient->reveal(),
            $this->cache->reveal(),
            $form,
            $form->defaultData()
        );
        $searchForm->submit();
    }

    public function testGuzzleExceptionsAreWrappedInSubmit()
    {
        $guzzleException = new \GuzzleHttp\Exception\TransferException('A Guzzle Exception');
        /** @var \Prophecy\Prophecy\ObjectProphecy $this->httpClient */
        $this->httpClient->request('GET', Argument::type('string'))->willThrow($guzzleException);
        $this->cache->get(Argument::type('string'))->willReturn(null);
        $form = $this->getSearchForm();
        try {
            $form->submit();
            $this->fail('No exception was thrown');
        } catch (\Prismic\Exception\RequestFailureException $e) {
            $this->assertSame($guzzleException, $e->getPrevious());
        }
    }

    private function prepareResponse(?string $body = null) : Response
    {
        $body = $body ? $body : '{"data":"data"}';
        $response = new Response(
            200,
            ['Cache-Control' => 'max-age=999'],
            $body
        );
        $this->httpClient->request('GET', Argument::type('string'))->willReturn($response);
        return $response;
    }

    public function testResponseJsonIsReturned()
    {
        $this->prepareResponse();
        $this->cache->get(Argument::type('string'))->willReturn(null);
        $this->cache->set(
            Argument::type('string'),
            Argument::type(\stdClass::class),
            999
        )->shouldBeCalled();
        $form = $this->getSearchForm();
        $response = $form->submit();
        $this->assertInstanceOf(\stdClass::class, $response);
        $this->assertSame('data', $response->data);
    }

    public function testCountReturnsIntWhenPresentInResponseBody()
    {
        $this->prepareResponse('{"total_results_size":10}');
        $this->cache->get(Argument::type('string'))->willReturn(null);
        $this->cache->set(
            Argument::type('string'),
            Argument::type(\stdClass::class),
            999
        )->shouldBeCalled();
        $form = $this->getSearchForm();
        $this->assertSame(10, $form->count());
    }

    public function testExceptionIsThrownForInvalidJson()
    {
        $this->expectException(\Prismic\Exception\RuntimeException::class);
        $this->expectExceptionMessage('Unable to decode json response');

        $this->prepareResponse('Invalid JSON String');
        $this->cache->get(Argument::type('string'))->willReturn(null);
        $this->cache->set()->shouldNotBeCalled();
        $form = $this->getSearchForm();
        $form->submit();
    }
}
