<?php

namespace App\Http\Controllers\API\V1\Mobile;

use App\Http\Controllers\API\V1\Mobile\Concerns\BuildsMobilePayloads;
use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\Item;
use App\Services\ProductImageMatcher;
use App\Services\PromotionPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageSearchController extends Controller
{
    use BuildsMobilePayloads;

    public function __construct(
        protected PromotionPricingService $promotionPricing,
        protected ProductImageMatcher $imageMatcher,
    ) {}

    /**
     * Search products by uploading an image.
     *
     * Accepts a product photo (file upload or base64), computes a semantic vision
     * embedding, then compares it against every product image.
     *
     * POST /api/products/search-by-image
     *   - image: file (jpg, jpeg, png, webp) max 4 MB
     *   - OR base64_image: string (base64-encoded image data, optional data-URI prefix)
     *   - threshold: int (0-64, max visual distance to consider a match, default 14; collages 18)
     *   - per_page: int (results per page, default 20)
     */
    public function search(Request $request): JsonResponse
    {
        // ── Validate input ──────────────────────────────────────────────
        $request->validate([
            'image' => ['required_without:base64_image', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'base64_image' => ['required_without:image', 'string'],
            'threshold' => ['nullable', 'integer', 'between:0,64'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        // 64 accepted every possible hash and caused unrelated products to be returned.
        $threshold = (int) ($request->input('threshold', ProductImageMatcher::DEFAULT_DISTANCE_THRESHOLD));
        $perPage = (int) ($request->input('per_page', 20));

        // ── Load the uploaded image into GD ─────────────────────────────
        $uploadedGd = $this->loadUploadedImage($request);

        if (! $uploadedGd) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to process the uploaded image. Please upload a valid JPG, PNG or WebP file.',
            ], 422);
        }

        $uploadedSignature = $this->imageMatcher->signature($uploadedGd, includeOcr: true);
        imagedestroy($uploadedGd);

        if (! $request->filled('threshold') && ! empty($uploadedSignature['multi_object_query'])) {
            $threshold = ProductImageMatcher::MULTI_OBJECT_DISTANCE_THRESHOLD;
        }

        // ── Collect all product images and compare ──────────────────────
        $matches = $this->imageMatcher->findMatchingItems($uploadedSignature, $threshold);

        // ── Paginate manually ───────────────────────────────────────────
        $total = count($matches);
        $page = max(1, (int) $request->input('page', 1));
        $sliced = array_slice($matches, ($page - 1) * $perPage, $perPage);

        // Eager-load items for the current page
        $itemIds = array_column($sliced, 'item_id');
        $items = Item::query()
            ->whereIn('id', $itemIds)
            ->with(['category', 'image', 'galleries'])
            ->get()
            ->keyBy('id');

        $results = [];
        $promotionPreviews = $this->promotionPricing->catalogPromotionPreviews($items->values());

        foreach ($sliced as $match) {
            $item = $items->get($match['item_id']);
            if ($item) {
                $payload = $this->mobileItemPayload($item, $promotionPreviews[(int) $item->id] ?? []);
                $payload['similarity_score'] = $match['similarity'];
                $payload['visual_distance'] = $match['distance'];
                $payload['hamming_distance'] = $match['distance'];
                $results[] = $payload;
            }
        }

        return response()->json([
            'success' => true,
            'message' => $total > 0 ? null : 'No visually similar products found in this catalog.',
            'data' => $results,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => (int) ceil($total / $perPage),
        ]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  Image Loading
    // ════════════════════════════════════════════════════════════════════

    /**
     * Create a GD image resource from the request (file upload or base64).
     */
    protected function loadUploadedImage(Request $request): ?\GdImage
    {
        if ($request->hasFile('image')) {
            return $this->gdFromPath($request->file('image')->getRealPath());
        }

        $base64 = (string) $request->input('base64_image', '');

        // Strip optional data-URI prefix  (e.g. "data:image/png;base64,...")
        if (str_contains($base64, ';base64,')) {
            $base64 = substr($base64, strpos($base64, ';base64,') + 8);
        }

        $binary = base64_decode($base64, strict: true);
        if ($binary === false) {
            return null;
        }

        $image = @imagecreatefromstring($binary);
        if ($image) {
            return $image;
        }

        return $this->gdFromFastApiConvert($binary);
    }

    /**
     * Create a GD image from an absolute file path.
     */
    protected function gdFromPath(string $path): ?\GdImage
    {
        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if (! $contents) {
            return null;
        }

        $image = @imagecreatefromstring($contents);
        if ($image) {
            return $image;
        }

        return $this->gdFromFastApiConvert($contents);
    }

    /**
     * Create a GD image from a Storage disk path.
     */
    protected function gdFromDisk(string $diskName, string $relativePath): ?\GdImage
    {
        $disk = Storage::disk($diskName);

        if (! $disk->exists($relativePath)) {
            return null;
        }

        $contents = $disk->get($relativePath);
        if (! $contents) {
            return null;
        }

        $image = @imagecreatefromstring($contents);
        if ($image) {
            return $image;
        }

        return $this->gdFromFastApiConvert($contents);
    }

    /**
     * Convert an image (JPEG, WebP, etc.) to PNG via FastAPI when PHP GD lacks format support.
     */
    protected function gdFromFastApiConvert(string $binary): ?\GdImage
    {
        try {
            $url = (string) config('services.vision_embeddings.url', '');
            if ($url === '') {
                return null;
            }
            $convertUrl = preg_replace('#/[^/]+$#', '/convert-to-png', $url);
            $response = Http::timeout(10)
                ->attach('image', $binary, 'image.bin')
                ->post($convertUrl);

            if ($response->successful()) {
                return @imagecreatefromstring($response->body()) ?: null;
            }
        } catch (\Throwable $e) {
            Log::debug('ImageSearch: FastAPI image conversion fallback failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    // ════════════════════════════════════════════════════════════════════
    //  Perceptual Hash (pHash)
    // ════════════════════════════════════════════════════════════════════

    /**
     * Compute a 64-bit perceptual hash of a GD image.
     *
     * Algorithm:
     *  1. Resize to 32×32 grayscale
     *  2. Compute a simplified DCT (8×8 low-frequency block)
     *  3. Compute the median of the 64 DCT coefficients
     *  4. Generate a 64-bit hash: 1 if coefficient >= median, else 0
     *
     * Returns the hash as a 16-char hex string (64 bits).
     */
    protected function computePHash(\GdImage $image): string
    {
        $size = 32;
        $hashSize = 8;

        // 1. Resize to 32×32 grayscale
        $small = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($small, 255, 255, 255);
        imagefilledrectangle($small, 0, 0, $size, $size, $white);
        imagecopyresampled($small, $image, 0, 0, 0, 0, $size, $size, imagesx($image), imagesy($image));

        // Convert to grayscale matrix
        $pixels = [];
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $rgb = imagecolorat($small, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $pixels[$y][$x] = (int) round($r * 0.299 + $g * 0.587 + $b * 0.114);
            }
        }
        imagedestroy($small);

        // 2. Simplified DCT – compute only the top-left 8×8 coefficients
        $dctValues = [];
        for ($u = 0; $u < $hashSize; $u++) {
            for ($v = 0; $v < $hashSize; $v++) {
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

        // 3. Compute median (exclude DC component at index 0)
        $dctWithoutDC = array_slice($dctValues, 1);
        sort($dctWithoutDC);
        $mid = (int) floor(count($dctWithoutDC) / 2);
        $median = count($dctWithoutDC) % 2 === 0
            ? ($dctWithoutDC[$mid - 1] + $dctWithoutDC[$mid]) / 2
            : $dctWithoutDC[$mid];

        // 4. Generate 64-bit hash
        $hash = '';
        foreach ($dctValues as $value) {
            $hash .= $value >= $median ? '1' : '0';
        }

        // Convert binary string to hex (16 hex chars = 64 bits)
        return str_pad(base_convert(substr($hash, 0, 32), 2, 16), 8, '0', STR_PAD_LEFT)
             .str_pad(base_convert(substr($hash, 32, 32), 2, 16), 8, '0', STR_PAD_LEFT);
    }

    /**
     * Compute the Hamming distance between two hex hash strings.
     */
    protected function hammingDistance(string $hash1, string $hash2): int
    {
        // Convert hex hashes to binary strings
        $bin1 = '';
        $bin2 = '';
        for ($i = 0; $i < 16; $i++) {
            $bin1 .= str_pad(base_convert($hash1[$i] ?? '0', 16, 2), 4, '0', STR_PAD_LEFT);
            $bin2 .= str_pad(base_convert($hash2[$i] ?? '0', 16, 2), 4, '0', STR_PAD_LEFT);
        }

        $distance = 0;
        for ($i = 0; $i < 64; $i++) {
            if (($bin1[$i] ?? '0') !== ($bin2[$i] ?? '0')) {
                $distance++;
            }
        }

        return $distance;
    }

    // ════════════════════════════════════════════════════════════════════
    //  Matching Logic
    // ════════════════════════════════════════════════════════════════════

    /**
     * Iterate through all item images, compute their pHash,
     * and return items whose Hamming distance is within the threshold.
     *
     * Returns an array sorted by distance (ascending):
     *   [ ['item_id' => int, 'distance' => int, 'similarity' => float], ... ]
     */
    protected function findMatchingItems(string $uploadedHash, int $threshold): array
    {
        $matches = [];
        $seen = [];  // track item IDs to avoid duplicates

        // Fetch all items that have an image, along with their gallery images
        $items = Item::query()
            ->whereNotNull('image_id')
            ->where('status', 'Active')
            ->with(['image', 'galleries'])
            ->get();

        foreach ($items as $item) {
            $bestDistance = PHP_INT_MAX;

            // ── Check the primary image ────────────────────────────
            if ($item->image && filled($item->image->name)) {
                $distance = $this->hashAndCompare($item->image->name, $uploadedHash);
                if ($distance !== null && $distance < $bestDistance) {
                    $bestDistance = $distance;
                }
            }

            // ── Check gallery images ───────────────────────────────
            foreach ($item->galleries->where('type', 'galleries') as $gallery) {
                if (filled($gallery->name)) {
                    $distance = $this->hashAndCompare($gallery->name, $uploadedHash);
                    if ($distance !== null && $distance < $bestDistance) {
                        $bestDistance = $distance;
                    }
                }
            }

            // ── Record if within threshold ─────────────────────────
            if ($bestDistance <= $threshold && ! isset($seen[$item->id])) {
                $seen[$item->id] = true;
                $matches[] = [
                    'item_id' => $item->id,
                    'distance' => $bestDistance,
                    'similarity' => round(1 - ($bestDistance / 64), 4),  // 1.0 = perfect match
                ];
            }
        }

        // Sort by distance ascending (best matches first)
        usort($matches, fn ($a, $b) => $a['distance'] <=> $b['distance']);

        return $matches;
    }

    /**
     * Load an image from the 'item' storage disk, compute its pHash,
     * and return the Hamming distance to the uploaded hash.
     */
    protected function hashAndCompare(string $imageName, string $uploadedHash): ?int
    {
        try {
            // Images stored under "assets/" are in the public directory
            if (str_starts_with($imageName, 'assets/') && is_file(public_path($imageName))) {
                $gd = $this->gdFromPath(public_path($imageName));
            } else {
                $gd = $this->gdFromDisk('item', $imageName);
            }

            if (! $gd) {
                return null;
            }

            $hash = $this->computePHash($gd);
            imagedestroy($gd);

            return $this->hammingDistance($uploadedHash, $hash);
        } catch (\Throwable $e) {
            Log::debug('ImageSearch: failed to process image', [
                'image' => $imageName,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
