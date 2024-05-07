(function (Drupal, debounce) {
  'use strict';
  Drupal.behaviors.breakpoints = {
    attach: function (context, settings) {

      // if url is admin, return
      if (window.location.href.indexOf('admin') > -1) { return; }

      const set = settings.responsive.breakpoints;
      let breakpoints = {};

      for (let key in set) {
        let newKey = key.split('.')[1]; // split the key by '.' and take the second part
        breakpoints[newKey] = set[key];
      }

      /**
       * Get the breakpoints that match the media query
       *
       * @param breakpoints
       * @returns {*[]}
       */
      function getMatches(breakpoints) {
        const matches = [];
        for (let key in breakpoints) {
          if (window.matchMedia(breakpoints[key]).matches) {
            matches.push(key);
          }
        }
        return matches;
      }

      const map = {
        'extra_small': ['layout--onecol'],
        'small': ['layout--onecol'],
        'medium': ['layout--onecol'],
        'large': ['layout--twocol-section'],
        'extra_large': ['layout--twocol-section']
      }

      function doLayout(breakpoints) {
        const matches = getMatches(breakpoints)
        const matched = matches.reduce((acc, key) => acc.concat(map[key]), [])
        // find all the unmatched as well - it should contain no duplicates and must not contain the matched value.
        const unmatched = Object.values(map).reduce((acc, val) => acc.concat(val), []).filter((val, index, self) => !matched.includes(val) && self.indexOf(val) === index)
        // console.log('matches', matches, 'matched', matched, 'unmatched', unmatched)

        const layout = document.querySelector('main .layout')

        // escape if there are not enough elements to switch between.
        if (layout.parentElement.children.length < 2) {
          return;
        }
        const url = window.location.href;
        const uri = window.location.pathname;
        const uria = uri.split('/').slice(1)

        if (uria[0] === 'admin') return
        if (uria[0] === 'node' && uria[2] === 'layout') return

        matched.forEach((classname) => {
          const selected = document.querySelectorAll(`.` + classname)
          if (selected !== undefined) {
            selected.forEach((item) => {
              item.classList.remove('visually-hidden')
              // console.log('Showing: ' + item)
            })
          }
        })
        unmatched.forEach((classname) => {
          const selected = document.querySelectorAll(`.` + classname)
          if (selected !== undefined) {
            selected.forEach((item) => {
              item.classList.add('visually-hidden')
              // console.log('Hiding: ' + item)
            })
          }
        })


      }
      doLayout(breakpoints);

      // @TODO disable on admin pages
      window.addEventListener('resize', () => {
        debounce(() => {
          // doLayout(breakpoints);
        }, 50, false)
        doLayout(breakpoints);

      });


    }
  };
})(Drupal, Drupal.debounce);