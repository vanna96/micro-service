<?php

namespace Tests\Unit;

use App\Models\Item;
use App\Services\ProductImageMatcher;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

#[RequiresPhpExtension('gd')]
class ProductImageMatcherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::fake([
            '*' => Http::response(['message' => 'disabled in local signature tests'], 503),
        ]);
    }

    public function test_default_threshold_cannot_accept_every_possible_image(): void
    {
        $this->assertLessThan(64, ProductImageMatcher::DEFAULT_DISTANCE_THRESHOLD);
    }

    public function test_it_matches_the_same_product_across_phone_and_catalogue_backgrounds(): void
    {
        $matcher = new ProductImageMatcher;
        $phonePhoto = $this->waterHeaterPhoto(360, 480, [239, 232, 220], 75, 45, 285, 435);
        $cataloguePhoto = $this->waterHeaterPhoto(420, 420, [255, 255, 255], 130, 25, 290, 395);
        $unrelatedProduct = $this->unrelatedProduct();

        try {
            $phoneSignature = $matcher->signature($phonePhoto);
            $matching = $matcher->compareSignatures($phoneSignature, $matcher->signature($cataloguePhoto));
            $unrelated = $matcher->compareSignatures($phoneSignature, $matcher->signature($unrelatedProduct));

            $this->assertGreaterThanOrEqual(2, count($phoneSignature['variants']), 'The main object should be isolated from the wall.');
            $this->assertLessThanOrEqual(ProductImageMatcher::DEFAULT_DISTANCE_THRESHOLD, $matching['distance']);
            $this->assertGreaterThan($unrelated['similarity'] + 0.12, $matching['similarity']);
            $this->assertGreaterThan(ProductImageMatcher::DEFAULT_DISTANCE_THRESHOLD, $unrelated['distance']);
        } finally {
            imagedestroy($phonePhoto);
            imagedestroy($cataloguePhoto);
            imagedestroy($unrelatedProduct);
        }
    }

    public function test_identical_images_have_zero_visual_distance(): void
    {
        $matcher = new ProductImageMatcher;
        $image = $this->waterHeaterPhoto(300, 400, [250, 250, 250], 70, 35, 230, 370);

        try {
            $signature = $matcher->signature($image);
            $comparison = $matcher->compareSignatures($signature, $signature);

            $this->assertSame(0, $comparison['distance']);
            $this->assertEqualsWithDelta(1.0, $comparison['similarity'], 0.0001);
        } finally {
            imagedestroy($image);
        }
    }

    public function test_semantic_embeddings_override_misleading_surface_similarity(): void
    {
        $matcher = new ProductImageMatcher;
        $image = $this->waterHeaterPhoto(300, 400, [250, 250, 250], 70, 35, 230, 370);

        try {
            $query = $matcher->signature($image);
            $sameProduct = $query;
            $differentProduct = $query;

            foreach ($query['variants'] as $index => $_variant) {
                $query['variants'][$index]['embedding'] = [1.0, 0.0, 0.0];
                $sameProduct['variants'][$index]['embedding'] = [0.99, 0.1, 0.0];
                $differentProduct['variants'][$index]['embedding'] = [0.0, 1.0, 0.0];
            }

            $matching = $matcher->compareSignatures($query, $sameProduct);
            $unrelated = $matcher->compareSignatures($query, $differentProduct);

            $this->assertTrue($matching['embedding_used']);
            $this->assertLessThanOrEqual(ProductImageMatcher::DEFAULT_DISTANCE_THRESHOLD, $matching['distance']);
            $this->assertGreaterThan(ProductImageMatcher::DEFAULT_DISTANCE_THRESHOLD, $unrelated['distance']);
        } finally {
            imagedestroy($image);
        }
    }

    public function test_ocr_sku_from_a_product_card_selects_the_exact_item(): void
    {
        $matcher = new ProductImageMatcher;
        $item = new Item([
            'sku' => 'FT-BCH-033',
            'name' => 'Multi-Function Bench Press Set',
        ]);
        $item->id = 36;
        $method = new \ReflectionMethod($matcher, 'findTextMatchingItems');

        $matches = $method->invoke($matcher, [$item], 'FT-BCH-O33 Multi-Function Bench Press Set');

        $this->assertSame(36, $matches[0]['item_id']);
        $this->assertSame(1.0, $matches[0]['similarity']);
    }

    public function test_exact_text_hit_is_merged_with_all_visual_matches(): void
    {
        $matcher = new ProductImageMatcher;
        $method = new \ReflectionMethod($matcher, 'mergeMatches');

        $matches = $method->invoke(
            $matcher,
            [[
                'item_id' => 14,
                'distance' => 0,
                'similarity' => 1.0,
            ]],
            [
                [
                    'item_id' => 14,
                    'distance' => 4,
                    'similarity' => 0.9375,
                ],
                [
                    'item_id' => 15,
                    'distance' => 8,
                    'similarity' => 0.875,
                ],
            ],
        );

        $this->assertCount(2, $matches);
        $this->assertSame(14, $matches[0]['item_id']);
        $this->assertSame(0, $matches[0]['distance']);
        $this->assertSame(15, $matches[1]['item_id']);
    }

    public function test_square_multi_product_query_builds_overlapping_regions(): void
    {
        $matcher = new ProductImageMatcher;
        $method = new \ReflectionMethod($matcher, 'multiObjectCrops');
        $image = imagecreatetruecolor(600, 600);
        $white = imagecolorallocate($image, 255, 255, 255);
        $green = imagecolorallocate($image, 90, 180, 100);
        $red = imagecolorallocate($image, 220, 45, 70);
        imagefill($image, 0, 0, $white);
        imagefilledrectangle($image, 70, 220, 250, 550, $green);
        imagefilledrectangle($image, 330, 270, 470, 550, $red);

        try {
            $crops = $method->invoke($matcher, $image);

            $this->assertCount(4, $crops);
            foreach ($crops as $crop) {
                imagedestroy($crop);
            }
        } finally {
            imagedestroy($image);
        }
    }

    public function test_multiple_region_crops_and_ocr_lines_identify_a_collage(): void
    {
        $matcher = new ProductImageMatcher;
        $method = new \ReflectionMethod($matcher, 'isMultiObjectQuery');

        $this->assertTrue($method->invoke($matcher, [true], array_fill(0, 8, 'product label')));
        $this->assertFalse($method->invoke($matcher, [true], array_fill(0, 7, 'product label')));
        $this->assertFalse($method->invoke($matcher, [], array_fill(0, 8, 'product label')));
    }

    public function test_signature_never_exceeds_the_vision_service_upload_limit(): void
    {
        $matcher = new ProductImageMatcher;
        $image = imagecreatetruecolor(520, 650);
        $white = imagecolorallocate($image, 255, 255, 255);
        $burgundy = imagecolorallocate($image, 105, 12, 38);
        imagefill($image, 0, 0, $white);
        imagefilledellipse($image, 260, 370, 330, 280, $burgundy);
        imagefilledrectangle($image, 175, 120, 205, 340, $burgundy);
        imagefilledrectangle($image, 315, 120, 345, 340, $burgundy);

        try {
            $signature = $matcher->signature($image, includeOcr: true);

            $this->assertLessThanOrEqual(8, count($signature['variants']));
        } finally {
            imagedestroy($image);
        }
    }

    public function test_zero_result_fallback_keeps_only_the_dominant_nearest_category(): void
    {
        $matcher = new ProductImageMatcher;
        $method = new \ReflectionMethod($matcher, 'nearestCategoryFallback');

        $matches = $method->invoke($matcher, [
            ['item_id' => 1, 'category_id' => 10, 'distance' => 23, 'similarity' => 0.63, 'embedding_used' => true],
            ['item_id' => 2, 'category_id' => 10, 'distance' => 25, 'similarity' => 0.61, 'embedding_used' => true],
            ['item_id' => 3, 'category_id' => 9, 'distance' => 26, 'similarity' => 0.60, 'embedding_used' => true],
            ['item_id' => 4, 'category_id' => 10, 'distance' => 26, 'similarity' => 0.59, 'embedding_used' => true],
        ]);

        $this->assertSame([1, 2, 4], array_column($matches, 'item_id'));
    }

    public function test_zero_result_fallback_rejects_a_category_tie(): void
    {
        $matcher = new ProductImageMatcher;
        $method = new \ReflectionMethod($matcher, 'nearestCategoryFallback');

        $matches = $method->invoke($matcher, [
            ['item_id' => 1, 'category_id' => 10, 'distance' => 18, 'similarity' => 0.72, 'embedding_used' => true],
            ['item_id' => 2, 'category_id' => 15, 'distance' => 18, 'similarity' => 0.71, 'embedding_used' => true],
            ['item_id' => 3, 'category_id' => 10, 'distance' => 19, 'similarity' => 0.70, 'embedding_used' => true],
            ['item_id' => 4, 'category_id' => 15, 'distance' => 19, 'similarity' => 0.69, 'embedding_used' => true],
        ]);

        $this->assertSame([], $matches);
    }

    /** @param array{0: int, 1: int, 2: int} $background */
    private function waterHeaterPhoto(
        int $width,
        int $height,
        array $background,
        int $left,
        int $top,
        int $right,
        int $bottom,
    ): \GdImage {
        $image = imagecreatetruecolor($width, $height);
        $wall = imagecolorallocate($image, ...$background);
        $dark = imagecolorallocate($image, 48, 48, 47);
        $black = imagecolorallocate($image, 18, 18, 18);
        $white = imagecolorallocate($image, 245, 245, 245);
        $green = imagecolorallocate($image, 40, 210, 65);
        imagefill($image, 0, 0, $wall);

        // Tall dark appliance body, central dial, logo and status light.
        imagefilledrectangle($image, $left, $top + 20, $right, $bottom - 20, $dark);
        imagefilledellipse($image, (int) (($left + $right) / 2), $top + 20, $right - $left, 40, $dark);
        imagefilledellipse($image, (int) (($left + $right) / 2), $bottom - 20, $right - $left, 40, $dark);
        imagefilledellipse($image, (int) (($left + $right) / 2), (int) ($top + ($bottom - $top) * 0.67), (int) (($right - $left) * 0.42), (int) (($right - $left) * 0.42), $black);
        imagefilledrectangle($image, (int) (($left + $right) / 2 - 24), $top + 55, (int) (($left + $right) / 2 + 24), $top + 63, $white);
        imagefilledellipse($image, (int) (($left + $right) / 2), $top + 105, 8, 8, $green);

        return $image;
    }

    private function unrelatedProduct(): \GdImage
    {
        $image = imagecreatetruecolor(420, 420);
        $white = imagecolorallocate($image, 255, 255, 255);
        $red = imagecolorallocate($image, 220, 45, 45);
        $yellow = imagecolorallocate($image, 250, 205, 40);
        imagefill($image, 0, 0, $white);
        imagefilledellipse($image, 210, 210, 300, 180, $red);
        imagefilledellipse($image, 210, 210, 80, 80, $yellow);

        return $image;
    }
}
