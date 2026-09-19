<?php
/**
 * Placeholder avatar for a clinician with no uploaded photograph.
 *
 * Deliberately an abstract figure rather than an illustrated person: no face,
 * no hair, no skin tone, no build. Anything more specific would be picking a
 * gender, an ethnicity and an age for every doctor who has not uploaded a
 * photo yet, which is exactly what a placeholder must not do. What identifies
 * the role instead is the coat lapel and the stethoscope - profession, not
 * person.
 *
 * Every fill is `currentColor` at a different opacity, so the avatar inherits
 * whatever brand colour the caller is sitting in and needs no palette of its
 * own. Set the size and colour from the call site:
 *
 *     $view->partial('partials/doctor-avatar', [
 *         'view'  => $view,
 *         'class' => 'h-full w-full text-medical-700',
 *     ])
 *
 * Decorative by contract: every placement shows the clinician's name as
 * adjacent text, so the SVG is hidden from assistive technology rather than
 * announcing "image" on every card in a directory.
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var string                        $class
 */

declare(strict_types=1);

$class = $class ?? 'h-full w-full';
?>
<?php /*
  Everything is composed inside the inscribed circle, because the card frames
  slot this into a round mask - artwork that runs to the corners of the
  viewBox gets clipped away exactly where it carries the most meaning.
*/ ?>
<svg class="<?= $view->e($class) ?>" viewBox="0 0 96 96" fill="none"
     xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
    <?php /* Tinted disc, so the avatar still reads as a portrait slot when it
             is placed on a white card rather than inside a ring. */ ?>
    <circle cx="48" cy="48" r="48" fill="currentColor" opacity=".10"/>

    <?php /* Head. Sits high enough that the coat meets it at the neck - a gap
             between the two reads as two shapes rather than one figure. */ ?>
    <circle cx="48" cy="33" r="15" fill="currentColor" opacity=".68"/>

    <?php /* Coat. Shoulders rise to meet the head, and the silhouette is drawn
             rather than arced so it reads as tailoring. */ ?>
    <path d="M6 96c0-24 18.8-43 42-43s42 19 42 43z" fill="currentColor" opacity=".46"/>

    <?php /* Lapel notch. */ ?>
    <path d="M48 53 40 60l8 16 8-16z" fill="currentColor" opacity=".18"/>

    <?php /* Stethoscope, worn around the neck: a symmetrical U with the chest
             piece hanging at the bottom. Drawn in the disc's own light tone
             rather than in currentColor, because on top of the coat that is
             what makes it legible at 40px in a search result - and it is the
             element that identifies the role without implying a person. */ ?>
    <path d="M37 60c-3.8 12.4.2 22.6 11 25.4 10.8-2.8 14.8-13 11-25.4"
          stroke="#fff" stroke-width="3.4" stroke-linecap="round" opacity=".82"/>
    <circle cx="48" cy="88" r="5.2" fill="#fff" opacity=".82"/>
    <circle cx="48" cy="88" r="2.2" fill="currentColor" opacity=".45"/>
</svg>
