/**
 * Each Gravity Form renders once per page, and its later placements are empty slots. The form moves into a slot as it
 * nears the viewport, or straight away when the slot is revealed (a modal opening, a tab or accordion showing it),
 * keeping the visitor's answers and step. While its current slot is near the viewport or it's submitting, scrolling
 * leaves it put, and the slot it leaves keeps its height so the page above doesn't jump. When its slot is hidden (a
 * modal closing), it moves to a shown slot, wherever that is.
 */
(function () {
  if (!('IntersectionObserver' in window)) {
    return;
  }

  var groups = {};
  var allSlots = [];
  var checks = [];

  document.querySelectorAll('.gf-mis-slot').forEach(function (slot) {
    var id = slot.getAttribute('data-gf-mis-form');
    (groups[id] = groups[id] || []).push(slot);
  });

  // Computed visibility is inherited, so it covers a hidden ancestor. Opacity isn't checked: scroll animations fade
  // sections in from 0, which would read as a reveal
  var isShown = function (slot) {
    return slot.getClientRects().length > 0 && getComputedStyle(slot).visibility === 'visible';
  };

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
    var shown = new Map();
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
          return slot !== holder && near.has(slot) && isShown(slot);
        })[0];

        if (target && !(near.has(holder) && isShown(holder))) {
          moveTo(target);
        }
      },
      { rootMargin: '50% 0px' },
    );

    // A slot going from hidden to shown has been revealed on purpose, e.g. a modal opening
    checks.push(function () {
      slots.forEach(function (slot) {
        var isShownNow = isShown(slot);

        if (isShownNow && shown.get(slot) === false) {
          moveTo(slot);
        }

        shown.set(slot, isShownNow);
      });

      if (!shown.get(holder)) {
        var candidates = slots.filter(function (slot) {
          return shown.get(slot);
        });
        var target =
          candidates.filter(function (slot) {
            return near.has(slot);
          })[0] || candidates[0];

        if (target) {
          moveTo(target);
        }
      }
    });

    slots.forEach(function (slot) {
      allSlots.push(slot);
      shown.set(slot, isShown(slot));
      intersection.observe(slot);

      // The link in a slot without the form scrolls to wherever the form is now
      linkIn(slot).addEventListener('click', function (event) {
        event.preventDefault();
        holder.scrollIntoView({ behavior: 'smooth', block: 'center' });
      });
    });
  });

  if (!checks.length) {
    return;
  }

  var scheduled = false;
  var schedule = function () {
    if (scheduled) {
      return;
    }

    scheduled = true;
    requestAnimationFrame(function () {
      scheduled = false;
      checks.forEach(function (check) {
        check();
      });
    });
  };

  // Only a change to a slot or one of its ancestors can show or hide it
  var holdsSlot = function (node) {
    return allSlots.some(function (slot) {
      return node.contains(slot);
    });
  };

  // Display changes resize the slot; visibility changes come from an attribute, a transition ending or a breakpoint
  if ('ResizeObserver' in window) {
    var resize = new ResizeObserver(schedule);
    allSlots.forEach(function (slot) {
      resize.observe(slot);
    });
  }

  window.addEventListener('resize', schedule);

  new MutationObserver(function (records) {
    if (
      records.some(function (record) {
        return holdsSlot(record.target);
      })
    ) {
      schedule();
    }
  }).observe(document.documentElement, { attributes: true, subtree: true });

  ['transitionend', 'animationend'].forEach(function (type) {
    document.addEventListener(
      type,
      function (event) {
        if (event.target instanceof Node && holdsSlot(event.target)) {
          schedule();
        }
      },
      true,
    );
  });

  schedule();
})();
