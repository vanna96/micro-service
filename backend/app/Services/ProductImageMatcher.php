<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProductImageMatcher
{
    /** Keep this aligned with the vision service's multipart upload limit. */
    private const MAX_SIGNATURE_VARIANTS = 8;

    /** Require a strong visual match for ordinary single-product queries. */
    public const DEFAULT_DISTANCE_THRESHOLD = 14;

    /** Collages need a little more distance because each product occupies less of the frame. */
    public const MULTI_OBJECT_DISTANCE_THRESHOLD = 18;

    private const FALLBACK_MAX_DISTANCE = 12;

    /** A zero-result query may use a small, category-consistent nearest group. */
    private const NEAREST_CATEGORY_MAX_DISTANCE = 26;

    private const NEAREST_CATEGORY_LIMIT = 4;

    private const NEAREST_CATEGORY_DISTANCE_WINDOW = 3;

    /**
     * Build a visual signature for an image. In addition to the complete photo,
     * keep a foreground crop when the image has a reasonably uniform border.
     * This makes phone photos comparable with clean catalogue images.
     *
     * @return array{variants: array<int, array<string, mixed>>, ocr_text: string, multi_object_query: bool}
     */
    public function signature(\GdImage $image, bool $includeOcr = false): array
    {
        $images = [$image];
        $derivedImages = [];
        $foreground = $this->foregroundCrop($image);

        if ($foreground) {
            $derivedImages[] = $foreground;
        }

        // A customer may upload a screenshot of a product card rather than a
        // clean photo. Embed several likely media regions so surrounding UI,
        // badges, prices and text do not overwhelm the product itself.
        array_push($derivedImages, ...$this->screenshotCrops($image));

        // The storefront cropper produces a square image. When a customer
        // uploads a product collage, embed overlapping groups independently so
        // one composite scene does not dilute every individual product signal.
        // Stored catalogue images do not need these query-only regions.
        $multiObjectCrops = [];
        $multiObjectVariantStart = null;
        if ($includeOcr) {
            $multiObjectVariantStart = 1 + count($derivedImages);
            $multiObjectCrops = $this->multiObjectCrops($image);
            array_push($derivedImages, ...$multiObjectCrops);
        }

        // A tall, near-square upload can qualify for foreground, screenshot and
        // collage crops at the same time. The vision service accepts at most
        // eight files; exceeding that limit used to discard every embedding and
        // silently fall back to strict hashes, which returned no matches.
        if (count($derivedImages) >= self::MAX_SIGNATURE_VARIANTS) {
            $discardedImages = array_splice(
                $derivedImages,
                self::MAX_SIGNATURE_VARIANTS - 1,
            );
            foreach ($discardedImages as $discardedImage) {
                imagedestroy($discardedImage);
            }
        }
        array_push($images, ...$derivedImages);

        $analysis = $this->analyzeImages($images, $includeOcr);
        $embeddings = $analysis['embeddings'];
        $multiObjectQuery = $this->isMultiObjectQuery($multiObjectCrops, $analysis['texts']);
        $variants = [];
        foreach ($images as $index => $variantImage) {
            // Single products near a square aspect ratio also qualify for the
            // provisional collage crops. Discard those regions unless OCR
            // confirms a multi-product layout; isolated straps and handles can
            // otherwise make a handbag look more like a belt.
            if (! $multiObjectQuery
                && $multiObjectVariantStart !== null
                && $index >= $multiObjectVariantStart) {
                continue;
            }
            $variants[] = $this->describe($variantImage, $embeddings[$index] ?? null);
        }

        foreach ($derivedImages as $derivedImage) {
            imagedestroy($derivedImage);
        }

        return [
            'variants' => $variants,
            'ocr_text' => implode(' ', $analysis['texts']),
            'multi_object_query' => $multiObjectQuery,
        ];
    }

    private function isMultiObjectQuery(array $regionCrops, array $ocrLines): bool
    {
        return $regionCrops !== [] && count($ocrLines) >= 8;
    }

    /** @return array<int, \GdImage> */
    private function screenshotCrops(\GdImage $image): array
    {
        $width = imagesx($image);
        $height = imagesy($image);

        // Product-card screenshots are normally tall. Avoid adding arbitrary
        // crops to regular landscape or square catalogue photography.
        if ($width < 240 || $height < 360 || ($width / $height) > 0.85) {
            return [];
        }

        $regions = [
            [0.10, 0.06, 0.84, 0.50],
            [0.18, 0.14, 0.78, 0.38],
            [0.08, 0.20, 0.86, 0.42],
        ];
        $crops = [];

        foreach ($regions as [$left, $top, $cropWidth, $cropHeight]) {
            $crop = imagecrop($image, [
                'x' => (int) round($width * $left),
                'y' => (int) round($height * $top),
                'width' => (int) round($width * $cropWidth),
                'height' => (int) round($height * $cropHeight),
            ]);
            if ($crop) {
                $crops[] = $crop;
            }
        }

        return $crops;
    }

    /** @return array<int, \GdImage> */
    private function multiObjectCrops(\GdImage $image): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $aspectRatio = $width / max(1, $height);

        if ($width < 360 || $height < 360 || $aspectRatio < 0.80 || $aspectRatio > 1.40) {
            return [];
        }

        // Overlapping regions retain product context while separating the left,
        // centre and right groups commonly found in marketplace collages.
        $regions = [
            [0.04, 0.20, 0.54, 0.78],
            [0.23, 0.20, 0.54, 0.78],
            [0.42, 0.20, 0.54, 0.78],
            [0.06, 0.28, 0.88, 0.70],
        ];
        $crops = [];

        foreach ($regions as [$left, $top, $cropWidth, $cropHeight]) {
            $crop = imagecrop($image, [
                'x' => (int) round($width * $left),
                'y' => (int) round($height * $top),
                'width' => (int) round($width * $cropWidth),
                'height' => (int) round($height * $cropHeight),
            ]);

            if ($crop) {
                $crops[] = $crop;
            }
        }

        return $crops;
    }

    /**
     * Find active items with a sufficiently similar product image.
     *
     * The threshold remains a 0-64 distance for API compatibility, but the
     * distance is derived from a UNICOM vision embedding when the vision service
     * is available. The local hash/colour signature remains as a fail-safe.
     *
     * @param  array{variants: array<int, array{phash: string, dhash: string, histogram: array<int, float>, aspect_ratio: float}>}  $uploadedSignature
     * @return array<int, array{item_id: int, distance: int, similarity: float}>
     */
    public function findMatchingItems(array $uploadedSignature, int $threshold): array
    {
        $visualMatches = [];
        $visualCandidates = [];

        $items = Item::query()
            ->whereNotNull('image_id')
            ->where('status', 'Active')
            ->with(['image', 'galleries'])
            ->get();

        $textMatches = $this->findTextMatchingItems($items, (string) ($uploadedSignature['ocr_text'] ?? ''));

        foreach ($items as $item) {
            $best = null;

            foreach ($this->itemImageNames($item) as $imageName) {
                $comparison = $this->compareStoredImage($imageName, $uploadedSignature);
                if ($comparison && ($best === null || $comparison['similarity'] > $best['similarity'])) {
                    $best = $comparison;
                }
            }

            if (! $best) {
                continue;
            }

            $visualCandidates[] = [
                'item_id' => (int) $item->id,
                'category_id' => (int) $item->category_id,
                'distance' => $best['distance'],
                'similarity' => round($best['similarity'], 4),
                'embedding_used' => ! empty($best['embedding_used']),
            ];

            $maximumDistance = ! empty($best['embedding_used'])
                ? $threshold
                : min($threshold, self::FALLBACK_MAX_DISTANCE);

            if ($best['distance'] <= $maximumDistance) {
                $visualMatches[] = [
                    'item_id' => (int) $item->id,
                    'distance' => $best['distance'],
                    'similarity' => round($best['similarity'], 4),
                ];
            }
        }

        if ($visualMatches === [] && $textMatches === []) {
            $visualMatches = $this->nearestCategoryFallback($visualCandidates);
        }

        $matches = $this->mergeMatches($textMatches, $visualMatches);
        if ($matches === []) {
            return [];
        }

        // Uploaded photos and screenshots often include backgrounds, labels or
        // interface chrome. Once the closest catalogue item is known, use its
        // clean stored image to discover every other visually similar product.
        // OCR hits are all valid seeds; otherwise the strongest visual hit is.
        $seedItemIds = $textMatches !== []
            ? array_values(array_unique(array_column($textMatches, 'item_id')))
            : [$matches[0]['item_id']];
        $seedSignatures = [];

        foreach ($items->whereIn('id', $seedItemIds) as $seedItem) {
            foreach ($this->itemImageNames($seedItem) as $imageName) {
                $signature = $this->getStoredSignature($imageName);
                if ($signature) {
                    $seedSignatures[] = $signature;
                }
            }
        }

        if ($seedSignatures === []) {
            return $matches;
        }

        $similarMatches = [];
        foreach ($items as $item) {
            $best = null;

            foreach ($this->itemImageNames($item) as $imageName) {
                $candidateSignature = $this->getStoredSignature($imageName);
                if (! $candidateSignature) {
                    continue;
                }

                foreach ($seedSignatures as $seedSignature) {
                    $comparison = $this->compareSignatures($seedSignature, $candidateSignature);
                    if ($best === null || $comparison['similarity'] > $best['similarity']) {
                        $best = $comparison;
                    }
                }
            }

            if (! $best) {
                continue;
            }

            $maximumDistance = ! empty($best['embedding_used'])
                ? $threshold
                : min($threshold, self::FALLBACK_MAX_DISTANCE);

            if ($best['distance'] <= $maximumDistance) {
                $similarMatches[] = [
                    'item_id' => (int) $item->id,
                    'distance' => $best['distance'],
                    'similarity' => round($best['similarity'], 4),
                ];
            }
        }

        return $this->mergeMatches($matches, $similarMatches);
    }

    /**
     * Avoid an empty result when the nearest semantic candidates strongly agree
     * on a catalog category. This is deliberately bounded: it only considers
     * embedding-backed candidates within distance 26, examines at most four,
     * and requires the closest two candidates to agree on their category.
     *
     * @param  array<int, array{item_id: int, category_id: int, distance: int, similarity: float, embedding_used: bool}>  $candidates
     * @return array<int, array{item_id: int, distance: int, similarity: float}>
     */
    private function nearestCategoryFallback(array $candidates): array
    {
        $eligible = array_values(array_filter(
            $candidates,
            static fn (array $candidate): bool => $candidate['embedding_used']
                && $candidate['category_id'] > 0
                && $candidate['distance'] <= self::NEAREST_CATEGORY_MAX_DISTANCE,
        ));

        usort($eligible, static fn (array $left, array $right): int => $left['distance'] <=> $right['distance']
                ?: $right['similarity'] <=> $left['similarity']
                ?: $left['item_id'] <=> $right['item_id']
        );

        if ($eligible === []) {
            return [];
        }

        $bestDistance = $eligible[0]['distance'];
        $nearest = array_slice(array_values(array_filter(
            $eligible,
            static fn (array $candidate): bool => $candidate['distance']
                <= $bestDistance + self::NEAREST_CATEGORY_DISTANCE_WINDOW,
        )), 0, self::NEAREST_CATEGORY_LIMIT);

        if (count($nearest) < 2 || $nearest[0]['category_id'] !== $nearest[1]['category_id']) {
            return [];
        }

        $winningCategory = $nearest[0]['category_id'];

        return array_values(array_map(
            static fn (array $candidate): array => [
                'item_id' => $candidate['item_id'],
                'distance' => $candidate['distance'],
                'similarity' => $candidate['similarity'],
            ],
            array_filter(
                $nearest,
                static fn (array $candidate): bool => $candidate['category_id'] === $winningCategory,
            ),
        ));
    }

    /** @return array<int, string> */
    private function itemImageNames(Item $item): array
    {
        $imageNames = [];

        if ($item->image && filled($item->image->name)) {
            $imageNames[] = $item->image->name;
        }

        foreach ($item->galleries->where('type', 'galleries') as $gallery) {
            if (filled($gallery->name)) {
                $imageNames[] = $gallery->name;
            }
        }

        return array_values(array_unique($imageNames));
    }

    /**
     * Merge exact text hits with visual matches, retaining the strongest score
     * for each product. Exact recognition improves ranking but must never stop
     * the search for other visually similar products.
     *
     * @param  array<int, array{item_id: int, distance: int, similarity: float}>  ...$matchSets
     * @return array<int, array{item_id: int, distance: int, similarity: float}>
     */
    private function mergeMatches(array ...$matchSets): array
    {
        $matchesByItem = [];

        foreach ($matchSets as $matches) {
            foreach ($matches as $match) {
                $itemId = (int) $match['item_id'];
                $current = $matchesByItem[$itemId] ?? null;

                if ($current === null || $match['distance'] < $current['distance']) {
                    $matchesByItem[$itemId] = $match;
                }
            }
        }

        $matches = array_values($matchesByItem);
        usort($matches, static function (array $left, array $right): int {
            return $left['distance'] <=> $right['distance']
                ?: $left['item_id'] <=> $right['item_id'];
        });

        return $matches;
    }

    /**
     * @param  array{variants: array<int, array{phash: string, dhash: string, histogram: array<int, float>, aspect_ratio: float}>}  $left
     * @param  array{variants: array<int, array{phash: string, dhash: string, histogram: array<int, float>, aspect_ratio: float}>}  $right
     * @return array{distance: int, similarity: float, embedding_used: bool}
     */
    public function compareSignatures(array $left, array $right): array
    {
        $bestVisualSimilarity = 0.0;
        $bestEmbeddingSimilarity = null;

        foreach ($left['variants'] as $leftVariant) {
            foreach ($right['variants'] as $rightVariant) {
                $perceptualSimilarity = 1 - ($this->hammingDistance($leftVariant['phash'], $rightVariant['phash']) / 64);
                $gradientSimilarity = 1 - ($this->hammingDistance($leftVariant['dhash'], $rightVariant['dhash']) / 64);
                $colourSimilarity = $this->histogramIntersection($leftVariant['histogram'], $rightVariant['histogram']);
                $aspectSimilarity = min($leftVariant['aspect_ratio'], $rightVariant['aspect_ratio'])
                    / max($leftVariant['aspect_ratio'], $rightVariant['aspect_ratio']);

                $visualSimilarity = (0.50 * $perceptualSimilarity)
                    + (0.25 * $gradientSimilarity)
                    + (0.15 * $colourSimilarity)
                    + (0.10 * $aspectSimilarity);

                $bestVisualSimilarity = max($bestVisualSimilarity, $visualSimilarity);

                if (! empty($leftVariant['embedding']) && ! empty($rightVariant['embedding'])) {
                    $embeddingSimilarity = $this->cosineSimilarity(
                        $leftVariant['embedding'],
                        $rightVariant['embedding']
                    );
                    $bestEmbeddingSimilarity = max($bestEmbeddingSimilarity ?? -1.0, $embeddingSimilarity);
                }
            }
        }

        // UNICOM provides semantic image-retrieval similarity. A small structural weight
        // helps separate products from the same broad class with different shapes.
        $embeddingUsed = $bestEmbeddingSimilarity !== null;
        $bestSimilarity = $embeddingUsed
            ? (0.90 * $bestEmbeddingSimilarity) + (0.10 * $bestVisualSimilarity)
            : $bestVisualSimilarity;
        $bestSimilarity = max(0.0, min(1.0, $bestSimilarity));

        return [
            'distance' => (int) round((1 - $bestSimilarity) * 64),
            'similarity' => $bestSimilarity,
            'embedding_used' => $embeddingUsed,
        ];
    }

    /** @return array<string, mixed> */
    private function describe(\GdImage $image, ?array $embedding = null): array
    {
        return [
            'phash' => $this->perceptualHash($image),
            'dhash' => $this->differenceHash($image),
            'histogram' => $this->colourHistogram($image),
            'aspect_ratio' => imagesx($image) / max(1, imagesy($image)),
            'embedding' => $embedding,
        ];
    }

    /**
     * Locate the largest connected region that differs from the image border.
     * Returns null for busy photographs where a reliable foreground cannot be
     * isolated, leaving the full-image signature as the safe fallback.
     */
    private function foregroundCrop(\GdImage $image): ?\GdImage
    {
        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);
        if ($sourceWidth < 24 || $sourceHeight < 24) {
            return null;
        }

        $scale = min(1.0, 160 / max($sourceWidth, $sourceHeight));
        $width = max(16, (int) round($sourceWidth * $scale));
        $height = max(16, (int) round($sourceHeight * $scale));
        $preview = $this->resampleOnWhite($image, $width, $height);

        $borderColours = [];
        for ($x = 0; $x < $width; $x += 2) {
            $borderColours[] = $this->rgbAt($preview, $x, 0);
            $borderColours[] = $this->rgbAt($preview, $x, $height - 1);
        }
        for ($y = 1; $y < $height - 1; $y += 2) {
            $borderColours[] = $this->rgbAt($preview, 0, $y);
            $borderColours[] = $this->rgbAt($preview, $width - 1, $y);
        }

        $background = [];
        for ($channel = 0; $channel < 3; $channel++) {
            $values = array_column($borderColours, $channel);
            sort($values);
            $background[$channel] = $values[(int) floor(count($values) / 2)];
        }

        $borderDistances = array_map(
            fn (array $rgb): float => $this->colourDistance($rgb, $background),
            $borderColours
        );
        sort($borderDistances);
        $borderVariation = $borderDistances[(int) floor(count($borderDistances) * 0.75)];

        // A varied border means this is a scene rather than a product-on-background image.
        if ($borderVariation > 55) {
            imagedestroy($preview);

            return null;
        }

        $differenceThreshold = max(42.0, $borderVariation * 2.2 + 18.0);
        $mask = [];
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $mask[$y * $width + $x] = $this->colourDistance($this->rgbAt($preview, $x, $y), $background) >= $differenceThreshold;
            }
        }
        imagedestroy($preview);

        $visited = [];
        $largest = null;
        $directions = [[1, 0], [-1, 0], [0, 1], [0, -1]];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $start = $y * $width + $x;
                if (empty($mask[$start]) || isset($visited[$start])) {
                    continue;
                }

                $queue = [[$x, $y]];
                $visited[$start] = true;
                $head = 0;
                $component = ['count' => 0, 'min_x' => $x, 'max_x' => $x, 'min_y' => $y, 'max_y' => $y];

                while (isset($queue[$head])) {
                    [$currentX, $currentY] = $queue[$head++];
                    $component['count']++;
                    $component['min_x'] = min($component['min_x'], $currentX);
                    $component['max_x'] = max($component['max_x'], $currentX);
                    $component['min_y'] = min($component['min_y'], $currentY);
                    $component['max_y'] = max($component['max_y'], $currentY);

                    foreach ($directions as [$dx, $dy]) {
                        $nextX = $currentX + $dx;
                        $nextY = $currentY + $dy;
                        if ($nextX < 0 || $nextX >= $width || $nextY < 0 || $nextY >= $height) {
                            continue;
                        }
                        $next = $nextY * $width + $nextX;
                        if (! empty($mask[$next]) && ! isset($visited[$next])) {
                            $visited[$next] = true;
                            $queue[] = [$nextX, $nextY];
                        }
                    }
                }

                if ($largest === null || $component['count'] > $largest['count']) {
                    $largest = $component;
                }
            }
        }

        if ($largest === null || $largest['count'] < ($width * $height * 0.025)) {
            return null;
        }

        $boxWidth = $largest['max_x'] - $largest['min_x'] + 1;
        $boxHeight = $largest['max_y'] - $largest['min_y'] + 1;
        $boxAreaRatio = ($boxWidth * $boxHeight) / ($width * $height);
        if ($boxWidth < 8 || $boxHeight < 8 || $boxAreaRatio > 0.94) {
            return null;
        }

        $paddingX = max(2, (int) round($boxWidth * 0.05));
        $paddingY = max(2, (int) round($boxHeight * 0.05));
        $left = max(0, $largest['min_x'] - $paddingX);
        $top = max(0, $largest['min_y'] - $paddingY);
        $right = min($width - 1, $largest['max_x'] + $paddingX);
        $bottom = min($height - 1, $largest['max_y'] + $paddingY);

        $crop = [
            'x' => (int) floor($left / $width * $sourceWidth),
            'y' => (int) floor($top / $height * $sourceHeight),
            'width' => max(1, (int) ceil(($right - $left + 1) / $width * $sourceWidth)),
            'height' => max(1, (int) ceil(($bottom - $top + 1) / $height * $sourceHeight)),
        ];
        $crop['width'] = min($crop['width'], $sourceWidth - $crop['x']);
        $crop['height'] = min($crop['height'], $sourceHeight - $crop['y']);

        return imagecrop($image, $crop) ?: null;
    }

    private function perceptualHash(\GdImage $image): string
    {
        $size = 32;
        $small = $this->resampleOnWhite($image, $size, $size);
        $pixels = [];

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                [$red, $green, $blue] = $this->rgbAt($small, $x, $y);
                $pixels[$y][$x] = ($red * 0.299) + ($green * 0.587) + ($blue * 0.114);
            }
        }
        imagedestroy($small);

        $dctValues = [];
        for ($u = 0; $u < 8; $u++) {
            for ($v = 0; $v < 8; $v++) {
                $sum = 0.0;
                for ($y = 0; $y < $size; $y++) {
                    for ($x = 0; $x < $size; $x++) {
                        $sum += $pixels[$y][$x]
                            * cos(M_PI / $size * ($x + 0.5) * $u)
                            * cos(M_PI / $size * ($y + 0.5) * $v);
                    }
                }
                $dctValues[] = $sum;
            }
        }

        $withoutDc = array_slice($dctValues, 1);
        sort($withoutDc);
        $median = $withoutDc[(int) floor(count($withoutDc) / 2)];

        return $this->bitsToHex(array_map(static fn (float $value): bool => $value >= $median, $dctValues));
    }

    private function differenceHash(\GdImage $image): string
    {
        $small = $this->resampleOnWhite($image, 9, 8);
        $bits = [];

        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                [$red1, $green1, $blue1] = $this->rgbAt($small, $x, $y);
                [$red2, $green2, $blue2] = $this->rgbAt($small, $x + 1, $y);
                $left = ($red1 * 0.299) + ($green1 * 0.587) + ($blue1 * 0.114);
                $right = ($red2 * 0.299) + ($green2 * 0.587) + ($blue2 * 0.114);
                $bits[] = $left >= $right;
            }
        }
        imagedestroy($small);

        return $this->bitsToHex($bits);
    }

    /** @return array<int, float> */
    private function colourHistogram(\GdImage $image): array
    {
        $small = $this->resampleOnWhite($image, 24, 24);
        $histogram = array_fill(0, 64, 0.0);

        for ($y = 0; $y < 24; $y++) {
            for ($x = 0; $x < 24; $x++) {
                [$red, $green, $blue] = $this->rgbAt($small, $x, $y);
                $index = intdiv($red, 64) * 16 + intdiv($green, 64) * 4 + intdiv($blue, 64);
                $histogram[min(63, $index)]++;
            }
        }
        imagedestroy($small);

        return array_map(static fn (float $count): float => $count / 576, $histogram);
    }

    public function getStoredSignature(string $imageName): ?array
    {
        try {
            if (str_starts_with($imageName, 'assets/') && is_file(public_path($imageName))) {
                $contents = file_get_contents(public_path($imageName));
            } else {
                $disk = Storage::disk('item');
                $contents = $disk->exists($imageName) ? $disk->get($imageName) : null;
            }

            if (! $contents) {
                return null;
            }

            $cacheKey = 'product-image-signature:unicom-v1:'.sha1($contents);
            $signature = Cache::store('file')->get($cacheKey);
            if (! is_array($signature)) {
                $image = @imagecreatefromstring($contents);
                if (! $image) {
                    return null;
                }
                $signature = $this->signature($image);
                if ($this->hasEmbedding($signature)) {
                    Cache::store('file')->put($cacheKey, $signature, now()->addDays(30));
                }
                imagedestroy($image);
            }

            return $signature;
        } catch (\Throwable $exception) {
            Log::debug('ImageSearch: failed to process image signature', [
                'image' => $imageName,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function compareStoredImage(string $imageName, array $uploadedSignature): ?array
    {
        try {
            $signature = $this->getStoredSignature($imageName);
            if (! $signature) {
                return null;
            }

            return $this->compareSignatures($uploadedSignature, $signature);
        } catch (\Throwable $exception) {
            Log::debug('ImageSearch: failed to process image', [
                'image' => $imageName,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<int, \GdImage>  $images
     * @return array{embeddings: array<int, array<int, float>>, texts: array<int, string>}
     */
    private function analyzeImages(array $images, bool $includeOcr): array
    {
        $url = (string) config('services.vision_embeddings.url', '');
        if ($url === '') {
            return ['embeddings' => [], 'texts' => []];
        }

        try {
            $request = Http::acceptJson()->timeout(
                (int) config('services.vision_embeddings.timeout', 45)
            );

            foreach ($images as $index => $image) {
                $stream = fopen('php://temp', 'w+b');
                if ($stream === false || ! imagepng($image, $stream, 6)) {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }

                    return ['embeddings' => [], 'texts' => []];
                }
                rewind($stream);
                $contents = stream_get_contents($stream);
                fclose($stream);
                if ($contents === false) {
                    return ['embeddings' => [], 'texts' => []];
                }
                $request = $request->attach('images', $contents, "variant-{$index}.png");
            }

            $response = $request->post($url, [
                'include_ocr' => $includeOcr ? 'true' : 'false',
            ]);
            if (! $response->successful()) {
                Log::warning('ImageSearch: vision embedding service rejected an image', [
                    'status' => $response->status(),
                ]);

                return ['embeddings' => [], 'texts' => []];
            }

            $embeddings = $response->json('embeddings');
            $texts = $response->json('texts');
            $ocrText = $response->json('ocr_text');
            $candidateSkus = $response->json('candidate_skus');

            $allTexts = is_array($texts) ? array_values(array_filter($texts, 'is_string')) : [];
            if (is_array($candidateSkus)) {
                foreach ($candidateSkus as $sku) {
                    if (is_string($sku) && ! in_array($sku, $allTexts, true)) {
                        $allTexts[] = $sku;
                    }
                }
            }
            if (is_string($ocrText) && filled($ocrText) && ! in_array($ocrText, $allTexts, true)) {
                $allTexts[] = $ocrText;
            }

            return [
                'embeddings' => is_array($embeddings) ? $embeddings : [],
                'texts' => $allTexts,
            ];
        } catch (\Throwable $exception) {
            Log::warning('ImageSearch: vision embedding service unavailable; using local fallback', [
                'error' => $exception->getMessage(),
            ]);

            return ['embeddings' => [], 'texts' => []];
        }
    }

    private function findTextMatchingItems(iterable $items, string $ocrText): array
    {
        $ocr = $this->comparableText($ocrText);
        if (strlen($ocr) < 4) {
            return [];
        }

        $ocrWithAmbiguousCharactersNormalized = str_replace('O', '0', $ocr);
        $matches = [];

        foreach ($items as $item) {
            $sku = $this->comparableText((string) $item->sku);
            $name = $this->comparableText((string) $item->name);
            $skuMatched = strlen($sku) >= 4 && (
                str_contains($ocr, $sku)
                || str_contains($ocrWithAmbiguousCharactersNormalized, str_replace('O', '0', $sku))
            );
            $nameMatched = strlen($name) >= 8 && str_contains($ocr, $name);

            if ($skuMatched || $nameMatched) {
                $matches[] = [
                    'item_id' => (int) $item->id,
                    'distance' => 0,
                    'similarity' => 1.0,
                ];
            }
        }

        return $matches;
    }

    private function comparableText(string $value): string
    {
        return preg_replace('/[^A-Z0-9]+/', '', strtoupper($value)) ?? '';
    }

    private function hasEmbedding(array $signature): bool
    {
        foreach ($signature['variants'] ?? [] as $variant) {
            if (! empty($variant['embedding'])) {
                return true;
            }
        }

        return false;
    }

    private function cosineSimilarity(array $left, array $right): float
    {
        if (count($left) !== count($right) || $left === []) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $leftMagnitude = 0.0;
        $rightMagnitude = 0.0;
        foreach ($left as $index => $leftValue) {
            $rightValue = $right[$index];
            $dotProduct += $leftValue * $rightValue;
            $leftMagnitude += $leftValue * $leftValue;
            $rightMagnitude += $rightValue * $rightValue;
        }

        $denominator = sqrt($leftMagnitude) * sqrt($rightMagnitude);

        return $denominator > 0 ? $dotProduct / $denominator : 0.0;
    }

    private function resampleOnWhite(\GdImage $image, int $width, int $height): \GdImage
    {
        $resampled = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($resampled, 255, 255, 255);
        imagefilledrectangle($resampled, 0, 0, $width - 1, $height - 1, $white);
        imagealphablending($resampled, true);
        imagecopyresampled($resampled, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));

        return $resampled;
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function rgbAt(\GdImage $image, int $x, int $y): array
    {
        $colour = imagecolorat($image, $x, $y);

        return [($colour >> 16) & 0xFF, ($colour >> 8) & 0xFF, $colour & 0xFF];
    }

    private function colourDistance(array $left, array $right): float
    {
        return sqrt(
            (($left[0] - $right[0]) ** 2)
            + (($left[1] - $right[1]) ** 2)
            + (($left[2] - $right[2]) ** 2)
        );
    }

    private function histogramIntersection(array $left, array $right): float
    {
        $intersection = 0.0;
        foreach ($left as $index => $value) {
            $intersection += min($value, $right[$index] ?? 0.0);
        }

        return $intersection;
    }

    private function hammingDistance(string $left, string $right): int
    {
        $distance = 0;
        for ($index = 0; $index < 16; $index++) {
            $xor = hexdec($left[$index] ?? '0') ^ hexdec($right[$index] ?? '0');
            $distance += substr_count(str_pad(decbin($xor), 4, '0', STR_PAD_LEFT), '1');
        }

        return $distance;
    }

    /** @param array<int, bool> $bits */
    private function bitsToHex(array $bits): string
    {
        $hex = '';
        for ($offset = 0; $offset < 64; $offset += 4) {
            $nibble = 0;
            for ($bit = 0; $bit < 4; $bit++) {
                $nibble = ($nibble << 1) | (! empty($bits[$offset + $bit]) ? 1 : 0);
            }
            $hex .= dechex($nibble);
        }

        return $hex;
    }
}
