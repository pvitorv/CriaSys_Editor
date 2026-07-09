<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Slide;
use App\Support\SafeJson;
use App\Support\Utf8;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SlideController extends Controller
{
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
            'transition_type' => ['nullable', 'in:fade,cut,slide'],
            'text_style' => ['nullable', 'array'],
        ]);

        $order = ($project->slides()->max('order') ?? -1) + 1;

        $slide = $project->slides()->create(array_merge($this->sanitizeSlideInput($data), [
            'order' => $order,
            'duration_seconds' => $data['duration_seconds'] ?? 5,
            'transition_type' => $data['transition_type'] ?? 'fade',
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
            'transition_type' => ['nullable', 'in:fade,cut,slide'],
            'text_style' => ['nullable', 'array'],
            'image_path' => ['nullable', 'string'],
        ]);

        $slide->update($this->sanitizeSlideInput($data));

        return SafeJson::response($this->slideResponse($slide->fresh()));
    }

    public function applyScript(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'blocks' => ['required', 'array', 'min:1'],
            'blocks.*.narration_text' => ['required', 'string'],
            'blocks.*.title' => ['nullable', 'string', 'max:255'],
        ]);

        $slides = $project->slides()->orderBy('order')->get();

        foreach ($data['blocks'] as $index => $block) {
            $block = $this->sanitizeSlideInput($block);
            if ($slides->has($index)) {
                $slides[$index]->update([
                    'narration_text' => $block['narration_text'],
                    'title' => $block['title'] ?? $slides[$index]->title,
                ]);
            } else {
                $project->slides()->create([
                    'order' => $index,
                    'title' => $block['title'] ?? 'Slide '.($index + 1),
                    'narration_text' => $block['narration_text'],
                    'duration_seconds' => 5,
                    'transition_type' => 'fade',
                ]);
            }
        }

        return SafeJson::response(
            $project->fresh('slides')->slides->map(fn (Slide $slide) => $this->slideResponse($slide))->values()
        );
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
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeSlideInput(array $data): array
    {
        foreach (['title', 'subtitle', 'body_text', 'narration_text', 'image_path'] as $field) {
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
        return Utf8::cleanArray($slide->toArray());
    }
}
