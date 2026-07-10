<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Slide;
use App\Services\Script\ScriptParser;
use App\Services\Slide\SlideDurationService;
use App\Support\SafeJson;
use App\Support\Utf8;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SlideController extends Controller
{
    public function __construct(private SlideDurationService $durations) {}
    public function index(Project $project): JsonResponse
    {
        return SafeJson::response(
            $project->slides->map(fn (Slide $slide) => $this->slideResponse($slide))->values()
        );
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'body_text' => ['nullable', 'string'],
            'narration_text' => ['nullable', 'string'],
            'duration_seconds' => ['nullable', 'numeric', 'min:0.5'],
            'duration_mode' => ['nullable', 'in:manual,video,narration'],
            'video_duration_seconds' => ['nullable', 'numeric', 'min:0'],
            'transition_type' => ['nullable', 'in:fade,cut,slide'],
            'text_style' => ['nullable', 'array'],
        ]);

        $order = ($project->slides()->max('order') ?? -1) + 1;

        $slide = $project->slides()->create(array_merge($this->sanitizeSlideInput($data), [
            'order' => $order,
            'duration_seconds' => $data['duration_seconds'] ?? 5,
            'transition_type' => $data['transition_type'] ?? 'fade',
            'text_style' => $data['text_style'] ?? (new Slide)->defaultTextStyle(),
            'duration_mode' => $data['duration_mode'] ?? 'narration',
        ]));

        return SafeJson::response($this->slideResponse($slide), 201);
    }

    public function update(Request $request, Project $project, Slide $slide): JsonResponse
    {
        abort_unless($slide->project_id === $project->id, 404);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'body_text' => ['nullable', 'string'],
            'narration_text' => ['nullable', 'string'],
            'duration_seconds' => ['nullable', 'numeric', 'min:0.5'],
            'duration_mode' => ['nullable', 'in:manual,video,narration'],
            'video_duration_seconds' => ['nullable', 'numeric', 'min:0'],
            'transition_type' => ['nullable', 'in:fade,cut,slide'],
            'text_style' => ['nullable', 'array'],
            'image_path' => ['nullable', 'string'],
            'video_path' => ['nullable', 'string'],
        ]);

        $hadVideo = (bool) $slide->video_path;
        $slide->update($this->sanitizeSlideInput($data));
        $slide->refresh();

        if (array_key_exists('video_path', $data)) {
            if ($data['video_path']) {
                if (! empty($data['video_duration_seconds'])) {
                    $slide->update([
                        'video_duration_seconds' => (float) $data['video_duration_seconds'],
                        'duration_mode' => $data['duration_mode'] ?? 'video',
                    ]);
                }
                $this->durations->applyVideoDuration($slide->fresh());
            } elseif ($hadVideo && ($slide->duration_mode === 'video' || ! ($data['duration_mode'] ?? null))) {
                $slide->update(['duration_mode' => 'narration', 'video_duration_seconds' => null]);
            }
        }

        if (($data['duration_mode'] ?? null) === 'narration') {
            $this->durations->applyAutomaticDurations($project->fresh('slides')->slides);
        }

        return SafeJson::response($this->slideResponse($slide->fresh()));
    }

    public function applyScript(Request $request, Project $project, ScriptParser $parser): JsonResponse
    {
        $data = $request->validate([
            'text' => ['nullable', 'string'],
            'blocks' => ['nullable', 'array', 'min:1'],
            'blocks.*.narration_text' => ['required_with:blocks', 'string'],
            'blocks.*.body_text' => ['nullable', 'string'],
            'blocks.*.kind' => ['nullable', 'string'],
            'blocks.*.section_title' => ['nullable', 'string'],
            'trim_extra_slides' => ['nullable', 'boolean'],
        ]);

        if (! empty($data['text'])) {
            $parsed = $parser->parse($data['text']);
            $blocks = $parsed['blocks'];
        } elseif (! empty($data['blocks'])) {
            $blocks = array_map(function (array $block) use ($parser) {
                return [
                    'narration_text' => $parser->formatNarrationText($block['narration_text']),
                    'body_text' => isset($block['body_text']) ? trim((string) $block['body_text']) : null,
                    'kind' => $block['kind'] ?? null,
                    'section_title' => $block['section_title'] ?? null,
                ];
            }, $data['blocks']);
        } else {
            return response()->json(['message' => 'Envie o roteiro em text ou blocks.'], 422);
        }

        if ($blocks === []) {
            return response()->json(['message' => 'Nenhum bloco de narração detectado no roteiro.'], 422);
        }

        $slides = $project->slides()->orderBy('order')->get();

        foreach ($blocks as $index => $block) {
            $block = $this->sanitizeSlideInput($block);
            $payload = $this->scriptBlockPayload($block, $slides->has($index) ? $slides[$index] : null);

            if ($slides->has($index)) {
                $slides[$index]->update($payload);
            } else {
                $project->slides()->create(array_merge([
                    'order' => $index,
                    'title' => 'Slide '.($index + 1),
                    'duration_seconds' => 5,
                    'duration_mode' => 'narration',
                    'transition_type' => 'fade',
                ], $payload));
            }
        }

        if ($request->boolean('trim_extra_slides')) {
            $project->slides()->orderBy('order')->get()->slice(count($blocks))->each(function (Slide $slide) {
                if (! $slide->image_path && ! $slide->video_path) {
                    $slide->delete();
                }
            });

            $project->slides()->orderBy('order')->get()->each(function (Slide $s, int $index) {
                $s->update(['order' => $index]);
            });
        }

        $this->durations->applyAutomaticDurations($project->fresh('slides')->slides);

        return SafeJson::response(
            $project->fresh('slides')->slides->map(fn (Slide $slide) => $this->slideResponse($slide))->values()
        );
    }

    public function recalculateDurations(Project $project): JsonResponse
    {
        $this->durations->applyAutomaticDurations($project->slides()->orderBy('order')->get());

        return SafeJson::response(
            $project->fresh('slides')->slides->map(fn (Slide $slide) => $this->slideResponse($slide))->values()
        );
    }

    public function parseScript(Request $request, Project $project, ScriptParser $parser): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string'],
        ]);

        return SafeJson::response($parser->parse($data['text']));
    }

    public function destroy(Project $project, Slide $slide): JsonResponse
    {
        abort_unless($slide->project_id === $project->id, 404);

        $slide->delete();

        $project->slides()->orderBy('order')->get()->each(function (Slide $s, int $index) {
            $s->update(['order' => $index]);
        });

        return response()->json(['message' => 'Slide removido.']);
    }

    public function reorder(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'slide_ids' => ['required', 'array'],
            'slide_ids.*' => ['integer', 'exists:slides,id'],
        ]);

        foreach ($data['slide_ids'] as $order => $slideId) {
            Slide::where('id', $slideId)->where('project_id', $project->id)->update(['order' => $order]);
        }

        return SafeJson::response(
            $project->fresh('slides')->slides->map(fn (Slide $slide) => $this->slideResponse($slide))->values()
        );
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function scriptBlockPayload(array $block, ?Slide $existing = null): array
    {
        $bodyText = $block['body_text'] ?? $block['narration_text'];
        $sectionTitle = trim((string) ($block['section_title'] ?? ''));
        if ($sectionTitle !== '' && ! str_contains($bodyText, $sectionTitle)) {
            $bodyText = $sectionTitle."\n".$bodyText;
        }

        $payload = [
            'narration_text' => $block['narration_text'],
            'body_text' => $bodyText,
            'text_style' => $this->scriptBlockTextStyle($existing),
        ];

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function scriptBlockTextStyle(?Slide $existing): array
    {
        $base = $existing?->text_style ?? [];
        $verticalAlign = $base['vertical_align'] ?? 'center';

        return [
            'body_color' => '#ffffff',
            'title_color' => '#ffffff',
            'body_size' => 12,
            'title_size' => 12,
            'align' => 'center',
            'vertical_align' => $verticalAlign,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeSlideInput(array $data): array
    {
        foreach (['title', 'subtitle', 'body_text', 'narration_text', 'image_path', 'video_path'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = Utf8::clean($data[$field]);
            }
        }

        if (isset($data['text_style']) && is_array($data['text_style'])) {
            $data['text_style'] = Utf8::cleanArray($data['text_style']);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function slideResponse(Slide $slide): array
    {
        $data = $slide->toArray();
        $data['text_style'] = $slide->normalizeTextStyle($slide->text_style ?? []);
        $data['duration_mode'] = $slide->duration_mode ?? 'narration';

        return Utf8::cleanArray($data);
    }
}
