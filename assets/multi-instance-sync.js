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

    // Returns false if the form can't move yet because it's submitting
    var moveTo = function (slot) {
      if (slot === holder) {
        return true;
      }

      if (submitting()) {
        return false;
      }

      holder.style.minHeight = holder.offsetHeight + 'px';
      linkIn(holder).style.display = '';
      holder.removeAttribute('id');

      slot.style.minHeight = '';
      linkIn(slot).style.display = 'none';
      slot.id = 'gf-mis-form-' + id;
      slot.prepend(form);

      holder = slot;

      return true;
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

    // A slot going from hidden to shown has been revealed on purpose, e.g. a modal opening. A reveal while the form
    // is submitting stays pending (the slot isn't marked shown) until the move can happen or the slot hides again.
    checks.push(function () {
      slots.forEach(function (slot) {
        var isShownNow = isShown(slot);

        if (isShownNow && shown.get(slot) === false && !moveTo(slot)) {
          setTimeout(schedule, 250);
          return;
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

        if (target && !moveTo(target)) {
          setTimeout(schedule, 250);
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

  // Checks run before the next paint, so a revealed slot never shows its link first, but at most every 100ms: any
  // attribute on the page can reveal a slot (e.g. a sibling selector), and animations change attributes every frame
  var scheduled = false;
  var lastCheck = 0;
  var check = function () {
    scheduled = false;
    lastCheck = Date.now();
    checks.forEach(function (run) {
      run();
    });
  };
  var schedule = function () {
    if (scheduled) {
      return;
    }

    scheduled = true;
    var wait = lastCheck + 100 - Date.now();

    if (wait > 0) {
      setTimeout(function () {
        requestAnimationFrame(check);
      }, wait);
    } else {
      requestAnimationFrame(check);
    }
  };

  // Display changes resize the slot; visibility changes come from an attribute, a transition ending, a breakpoint or
  // a :target change
  if ('ResizeObserver' in window) {
    var resize = new ResizeObserver(schedule);
    allSlots.forEach(function (slot) {
      resize.observe(slot);
    });
  }

  new MutationObserver(schedule).observe(document.documentElement, {
    attributes: true,
    subtree: true,
  });

  ['transitionend', 'animationend'].forEach(function (type) {
    document.addEventListener(type, schedule, true);
  });

  ['resize', 'hashchange'].forEach(function (type) {
    window.addEventListener(type, schedule);
  });

  schedule();
})();
