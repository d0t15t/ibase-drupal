(function (Drupal, debounce) {
  'use strict';
  Drupal.behaviors.breakpoints = {
    attach: function (context, settings) {

      const set = settings.responsive.breakpoints;
      let breakpoints = {};

      for (let key in set) {
        let newKey = key.split('.')[1]; // split the key by '.' and take the second part
        breakpoints[newKey] = set[key];
      }
      // remove 'extra_small' from the array
      // delete breakpoints.extra_small;

      function getMatches(breakpoints) {
        // return an array of matching breakpoints
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
        matched.forEach((classname) => {
          const selected = document.querySelector(`.` + classname)
          if (selected) {
            selected.classList.remove('visually-hidden')
            // console.log('Showing: ' + classname)
          }
        })
        unmatched.forEach((classname) => {
          const selected = document.querySelector(`.` + classname)
          if (selected) {
            selected.classList.add('visually-hidden')
            // console.log('Hiding: ' + classname)
          }
        })

        console.log('matches', matches, 'matched', matched, 'unmatched', unmatched)
      }

      doLayout(breakpoints);

      // @TODO disable on admin pages
      window.addEventListener('resize', () => {
        debounce(() => {
          doLayout(breakpoints);
        }, 50, false)
        // doLayout(breakpoints);

      });


    }
  };
})(Drupal, Drupal.debounce);