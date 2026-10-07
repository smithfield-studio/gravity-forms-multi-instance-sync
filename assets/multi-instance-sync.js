/**
 * Each Gravity Form renders once per page, and its later placements are empty slots. As one of those slots nears the
 * viewport, the form moves into it, keeping the visitor's answers and step. It stays put while its current slot is
 * near the viewport or it's submitting, and the slot it leaves keeps its height so the page above doesn't jump.
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
      return slot.querySelector('a[href="#gf-mis-form-' + id + '"]');
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

    var moveTo = function (slot) {
      holder.style.minHeight = holder.offsetHeight + 'px';
      linkIn(holder).style.display = '';
      holder.removeAttribute('id');

      slot.style.minHeight = '';
      linkIn(slot).style.display = 'none';
      slot.id = 'gf-mis-form-' + id;
      slot.prepend(form);

      holder = slot;
    };

    var observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          entry.isIntersecting ? near.add(entry.target) : near.delete(entry.target);
        });

        var target = slots.filter(function (slot) {
          return slot !== holder && near.has(slot);
        })[0];

        if (target && !near.has(holder) && !window['gf_submitting_' + id]) {
          moveTo(target);
        }
      },
      { rootMargin: '50% 0px' }
    );

    slots.forEach(function (slot) {
      observer.observe(slot);

      // The link in a slot without the form scrolls to wherever the form is now
      linkIn(slot).addEventListener('click', function (event) {
        event.preventDefault();
        holder.scrollIntoView({ behavior: 'smooth', block: 'center' });
      });
    });
  });
})();
