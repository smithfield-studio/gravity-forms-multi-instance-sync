/**
 * Each Gravity Form renders once per page, and its later placements are empty slots. The form moves into a slot as it
 * nears the viewport, or straight away when the slot is revealed (a modal opening, a tab or accordion showing it),
 * keeping the visitor's answers and step. While its current slot is near the viewport or it's submitting, scrolling
 * leaves it put, and the slot it leaves keeps its height so the page above doesn't jump.
 */
(function () {
  if (!('IntersectionObserver' in window)) {
    return;
  }

  var groups = {};

  document.querySelectorAll('.gf-mis-slot').forEach(function (slot) {
    var id = slot.getAttribute('data-gf-mis-form');
    (groups[id] = groups[id] || []).push(slot);
  });

  Object.keys(groups).forEach(function (id) {
    var slots = groups[id];
    var linkIn = function (slot) {
      return slot.querySelector('[data-gf-mis-link]');
    };
    var form = slots
      .map(function (slot) {
        return slot.querySelector('.gf-mis-slot__form');
      })
      .filter(Boolean)[0];

    if (slots.length < 2 || !form) {
      return;
    }

    // The inline script after each slot may already have moved it out of a hidden placement
    var holder = slots.filter(function (slot) {
      return slot.contains(form);
    })[0];
    var near = new Set();
    var submitting = function () {
      return window['gf_submitting_' + id];
    };

    var moveTo = function (slot) {
      if (slot === holder || submitting()) {
        return;
      }

      holder.style.minHeight = holder.offsetHeight + 'px';
      linkIn(holder).style.display = '';
      holder.removeAttribute('id');

      slot.style.minHeight = '';
      linkIn(slot).style.display = 'none';
      slot.id = 'gf-mis-form-' + id;
      slot.prepend(form);

      holder = slot;
    };

    var intersection = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            near.add(entry.target);
          } else {
            near.delete(entry.target);
          }
        });

        var target = slots.filter(function (slot) {
          return slot !== holder && near.has(slot);
        })[0];

        if (target && !near.has(holder)) {
          moveTo(target);
        }
      },
      { rootMargin: '50% 0px' },
    );

    // A slot going from hidden to shown has been revealed on purpose, e.g. a modal opening
    var shown = new Map();
    var reveal =
      'ResizeObserver' in window &&
      new ResizeObserver(function (entries) {
        entries.forEach(function (entry) {
          var isShown = entry.target.getClientRects().length > 0;

          if (isShown && shown.get(entry.target) === false) {
            moveTo(entry.target);
          }

          shown.set(entry.target, isShown);
        });
      });

    slots.forEach(function (slot) {
      intersection.observe(slot);

      if (reveal) {
        shown.set(slot, slot.getClientRects().length > 0);
        reveal.observe(slot);
      }

      // The link in a slot without the form scrolls to wherever the form is now
      linkIn(slot).addEventListener('click', function (event) {
        event.preventDefault();
        holder.scrollIntoView({ behavior: 'smooth', block: 'center' });
      });
    });
  });
})();
