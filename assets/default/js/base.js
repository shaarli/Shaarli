import Awesomplete from 'awesomplete';

/**
 * Find a parent element according to its tag and its attributes
 *
 * @param element    Element where to start the search
 * @param tagName    Expected parent tag name
 * @param attributes Associative array of expected attributes (name=>value).
 *
 * @returns Found element or null.
 */
function findParent(element, tagName, attributes) {
  const parentMatch = key => attributes[key] !== '' && element.getAttribute(key).indexOf(attributes[key]) !== -1;
  while (element) {
    if (element.tagName.toLowerCase() === tagName) {
      if (Object.keys(attributes).find(parentMatch)) {
        return element;
      }
    }
    element = element.parentElement;
  }
  return null;
}

/**
 * Ajax request to refresh the CSRF token.
 */
function refreshToken() {
  const xhr = new XMLHttpRequest();
  xhr.open('GET', '?do=token');
  xhr.onload = () => {
    const token = document.getElementById('token');
    token.setAttribute('value', xhr.responseText);
  };
  xhr.send();
}

function createAwesompleteInstance(element, tags = []) {
  const awesome = new Awesomplete(Awesomplete.$(element));
  // Tags are separated by a space
  awesome.filter = (text, input) => Awesomplete.FILTER_CONTAINS(text, input.match(/[^ ]*$/)[0]);
  // Insert new selected tag in the input
  awesome.replace = (text) => {
    const before = awesome.input.value.match(/^.+ \s*|/)[0];
    awesome.input.value = `${before}${text} `;
  };
  // Highlight found items
  awesome.item = (text, input) => Awesomplete.ITEM(text, input.match(/[^ ]*$/)[0]);
  // Don't display already selected items
  const reg = /(\w+) /g;
  let match;
  awesome.data = (item, input) => {
    while ((match = reg.exec(input))) {
      if (item === match[1]) {
        return '';
      }
    }
    return item;
  };
  awesome.minChars = 1;
  if (tags.length) {
    awesome.list = tags;
  }

  return awesome;
}

/**
 * Update awesomplete list of tag for all elements matching the given selector
 *
 * @param selector  CSS selector
 * @param tags      Array of tags
 * @param instances List of existing awesomplete instances
 */
function updateAwesompleteList(selector, tags, instances) {
  if (instances.length === 0) {
    // First load: create Awesomplete instances
    const elements = document.querySelectorAll(selector);
    [...elements].forEach((element) => {
      instances.push(createAwesompleteInstance(element, tags));
    });
  } else {
    // Update awesomplete tag list
    instances.map((item) => {
      item.list = tags;
      return item;
    });
  }
  return instances;
}

/**
 * html_entities in JS
 *
 * @see http://stackoverflow.com/questions/18749591/encode-html-entities-in-javascript
 */
function htmlEntities(str) {
  return str.replace(/[\u00A0-\u9999<>&]/gim, i => `&#${i.charCodeAt(0)};`);
}

/**
 * Add the class 'hidden' to city options not attached to the current selected continent.
 *
 * @param cities           List of <option> elements
 * @param currentContinent Current selected continent
 * @param reset            Set to true to reset the selected value
 */
function hideTimezoneCities(cities, currentContinent, reset = null) {
  let first = true;
  if (reset == null) {
    reset = false;
  }
  [...cities].forEach((option) => {
    if (option.getAttribute('data-continent') !== currentContinent) {
      option.className = 'hidden';
    } else {
      option.className = '';
      if (reset === true && first === true) {
        option.setAttribute('selected', 'selected');
        first = false;
      }
    }
  });
}

/**
 * Retrieve an element up in the tree from its class name.
 */
function getParentByClass(el, className) {
  const p = el.parentNode;
  if (p == null || p.classList.contains(className)) {
    return p;
  }
  return getParentByClass(p, className);
}

function toggleHorizontal() {
  [...document.getElementById('shaarli-menu').querySelectorAll('.menu-transform')].forEach((el) => {
    el.classList.toggle('pure-menu-horizontal');
  });
}

function toggleMenu(menu) {
  // set timeout so that the panel has a chance to roll up
  // before the menu switches states
  if (menu.classList.contains('open')) {
    setTimeout(toggleHorizontal, 500);
  } else {
    toggleHorizontal();
  }
  menu.classList.toggle('open');
  document.getElementById('menu-toggle').classList.toggle('x');
}

function closeMenu(menu) {
  if (menu.classList.contains('open')) {
    toggleMenu(menu);
  }
}

function toggleFold(button, description, thumb) {
  // Switch fold/expand - up = fold
  if (button.classList.contains('fa-chevron-up')) {
    button.title = document.getElementById('translation-expand').innerHTML;
    if (description != null) {
      description.style.display = 'none';
    }
    if (thumb != null) {
      thumb.style.display = 'none';
    }
  } else {
    button.title = document.getElementById('translation-fold').innerHTML;
    if (description != null) {
      description.style.display = 'block';
    }
    if (thumb != null) {
      thumb.style.display = 'block';
    }
  }
  button.classList.toggle('fa-chevron-down');
  button.classList.toggle('fa-chevron-up');
}

function removeClass(element, classname) {
  element.className = element.className.replace(new RegExp(`(?:^|\\s)${classname}(?:\\s|$)`), ' ');
}

function init(description) {
  function resize() {
    /* Fix jumpy resizing: https://stackoverflow.com/a/18262927/1484919 */
    const scrollTop = window.pageYOffset ||
      (document.documentElement || document.body.parentNode || document.body).scrollTop;

    description.style.height = 'auto';
    description.style.height = `${description.scrollHeight + 10}px`;

    window.scrollTo(0, scrollTop);
  }

  /* 0-timeout to get the already changed text */
  function delayedResize() {
    window.setTimeout(resize, 0);
  }

  const observe = (element, event, handler) => {
    element.addEventListener(event, handler, false);
  };
  observe(description, 'change', resize);
  observe(description, 'cut', delayedResize);
  observe(description, 'paste', delayedResize);
  observe(description, 'drop', delayedResize);
  observe(description, 'keydown', delayedResize);

  resize();
}

(() => {
  /**
   * Handle responsive menu.
   * Source: http://purecss.io/layouts/tucked-menu-vertical/
   */
  const menu = document.getElementById('shaarli-menu');
  const WINDOW_CHANGE_EVENT = ('onorientationchange' in window) ? 'orientationchange' : 'resize';

  const menuToggle = document.getElementById('menu-toggle');
  if (menuToggle != null) {
    menuToggle.addEventListener('click', () => toggleMenu(menu));
  }

  window.addEventListener(WINDOW_CHANGE_EVENT, () => closeMenu(menu));

  /**
   * Fold/Expand shaares description and thumbnail.
   */
  const foldAllButtons = document.getElementsByClassName('fold-all');
  const foldButtons = document.getElementsByClassName('fold-button');

  [...foldButtons].forEach((foldButton) => {
    // Retrieve description
    let description = null;
    let thumbnail = null;
    const linklistItem = getParentByClass(foldButton, 'linklist-item');
    if (linklistItem != null) {
      description = linklistItem.querySelector('.linklist-item-description');
      thumbnail = linklistItem.querySelector('.linklist-item-thumbnail');
      if (description != null || thumbnail != null) {
        foldButton.style.display = 'inline';
      }
    }

    foldButton.addEventListener('click', (event) => {
      event.preventDefault();
      toggleFold(event.target, description, thumbnail);
    });
  });

  if (foldAllButtons != null) {
    [].forEach.call(foldAllButtons, (foldAllButton) => {
      foldAllButton.addEventListener('click', (event) => {
        event.preventDefault();
        const state = foldAllButton.firstElementChild.getAttribute('class').indexOf('down') !== -1 ? 'down' : 'up';
        [].forEach.call(foldButtons, (foldButton) => {
          if ((foldButton.firstElementChild.classList.contains('fa-chevron-up') && state === 'down')
            || (foldButton.firstElementChild.classList.contains('fa-chevron-down') && state === 'up')
          ) {
            return;
          }
          // Retrieve description
          let description = null;
          let thumbnail = null;
          const linklistItem = getParentByClass(foldButton, 'linklist-item');
          if (linklistItem != null) {
            description = linklistItem.querySelector('.linklist-item-description');
            thumbnail = linklistItem.querySelector('.linklist-item-thumbnail');
            if (description != null || thumbnail != null) {
              foldButton.style.display = 'inline';
            }
          }

          toggleFold(foldButton.firstElementChild, description, thumbnail);
        });
        foldAllButton.firstElementChild.classList.toggle('fa-chevron-down');
        foldAllButton.firstElementChild.classList.toggle('fa-chevron-up');
        foldAllButton.title = state === 'down'
          ? document.getElementById('translation-fold-all').innerHTML
          : document.getElementById('translation-expand-all').innerHTML;
      });
    });
  }

  /**
   * Confirmation message before deletion.
   */
  const deleteLinks = document.querySelectorAll('.confirm-delete');
  [...deleteLinks].forEach((deleteLink) => {
    deleteLink.addEventListener('click', (event) => {
      if (!confirm(document.getElementById('translation-delete-link').innerHTML)) {
        event.preventDefault();
      }
    });
  });

  /**
   * Close alerts
   */
  const closeLinks = document.querySelectorAll('.pure-alert-close');
  [...closeLinks].forEach((closeLink) => {
    closeLink.addEventListener('click', (event) => {
      const alert = getParentByClass(event.target, 'pure-alert-closable');
      alert.style.display = 'none';
    });
  });

  /**
   * New version dismiss.
   * Hide the message for one week using localStorage.
   */
  const newVersionDismiss = document.getElementById('new-version-dismiss');
  const newVersionMessage = document.querySelector('.new-version-message');
  if (newVersionMessage != null
    && localStorage.getItem('newVersionDismiss') != null
    && parseInt(localStorage.getItem('newVersionDismiss'), 10) + (7 * 24 * 60 * 60 * 1000) > (new Date()).getTime()
  ) {
    newVersionMessage.style.display = 'none';
  }
  if (newVersionDismiss != null) {
    newVersionDismiss.addEventListener('click', () => {
      localStorage.setItem('newVersionDismiss', (new Date()).getTime().toString());
    });
  }

  const hiddenReturnurl = document.getElementsByName('returnurl');
  if (hiddenReturnurl != null) {
    hiddenReturnurl.value = window.location.href;
  }

  /**
   * Autofocus text fields
   */
  const autofocusElements = document.querySelectorAll('.autofocus');
  let breakLoop = false;
  [].forEach.call(autofocusElements, (autofocusElement) => {
    if (autofocusElement.value === '' && !breakLoop) {
      autofocusElement.focus();
      breakLoop = true;
    }
  });

  /**
   * Handle sub menus/forms
   */
  const openers = document.getElementsByClassName('subheader-opener');
  if (openers != null) {
    [...openers].forEach((opener) => {
      opener.addEventListener('click', (event) => {
        event.preventDefault();

        const id = opener.getAttribute('data-open-id');
        const sub = document.getElementById(id);

        if (sub != null) {
          [...document.getElementsByClassName('subheader-form')].forEach((element) => {
            if (element !== sub) {
              removeClass(element, 'open');
            }
          });

          sub.classList.toggle('open');
        }
      });
    });
  }

  /**
   * Remove CSS target padding (for fixed bar)
   */
  if (location.hash !== '') {
    const anchor = document.getElementById(location.hash.substr(1));
    if (anchor != null) {
      const padsize = anchor.clientHeight;
      window.scroll(0, window.scrollY - padsize);
      anchor.style.paddingTop = '0';
    }
  }

  /**
   * Text area resizer
   */
  const description = document.getElementById('lf_description');

  if (description != null) {
    init(description);
    // Submit editlink form with CTRL + Enter in the text area.
    description.addEventListener('keydown', (event) => {
      if (event.ctrlKey && event.keyCode === 13) {
        document.getElementById('button-save-edit').click();
      }
    });
  }

  /**
   * Bookmarklet alert
   */
  const bookmarkletLinks = document.querySelectorAll('.bookmarklet-link');
  const bkmMessage = document.getElementById('bookmarklet-alert');
  [].forEach.call(bookmarkletLinks, (link) => {
    link.addEventListener('click', (event) => {
      event.preventDefault();
      alert(bkmMessage.value);
    });
  });

  const continent = document.getElementById('continent');
  const city = document.getElementById('city');
  if (continent != null && city != null) {
    continent.addEventListener('change', () => {
      hideTimezoneCities(city, continent.options[continent.selectedIndex].value, true);
    });
    hideTimezoneCities(city, continent.options[continent.selectedIndex].value, false);
  }

  /**
   * Bulk actions
   */
  const linkCheckboxes = document.querySelectorAll('.link-checkbox');
  const bar = document.getElementById('actions');
  [...linkCheckboxes].forEach((checkbox) => {
    checkbox.style.display = 'inline-block';
    checkbox.addEventListener('change', () => {
      const linkCheckedCheckboxes = document.querySelectorAll('.link-checkbox:checked');
      const count = [...linkCheckedCheckboxes].length;
      if (count === 0 && bar.classList.contains('open')) {
        bar.classList.toggle('open');
      } else if (count > 0 && !bar.classList.contains('open')) {
        bar.classList.toggle('open');
      }
    });
  });

  const deleteButton = document.getElementById('actions-delete');
  const token = document.getElementById('token');
  if (deleteButton != null && token != null) {
    deleteButton.addEventListener('click', (event) => {
      event.preventDefault();

      const links = [];
      const linkCheckedCheckboxes = document.querySelectorAll('.link-checkbox:checked');
      [...linkCheckedCheckboxes].forEach((checkbox) => {
        links.push({
          id: checkbox.value,
          title: document.querySelector(`.linklist-item[data-id="${checkbox.value}"] .linklist-link`).innerHTML,
        });
      });

      let message = `Are you sure you want to delete ${links.length} links?\n`;
      message += 'This action is IRREVERSIBLE!\n\nTitles:\n';
      const ids = [];
      links.forEach((item) => {
        message += `  - ${item.title}\n`;
        ids.push(item.id);
      });

      if (window.confirm(message)) {
        window.location = `?delete_link&lf_linkdate=${ids.join('+')}&token=${token.value}`;
      }
    });
  }

  /**
   * Select all button
   */
  const selectAllButtons = document.querySelectorAll('.select-all-button');
  [...selectAllButtons].forEach((selectAllButton) => {
    selectAllButton.addEventListener('click', (e) => {
      e.preventDefault();
      const checked = selectAllButton.classList.contains('filter-off');
      [...selectAllButtons].forEach((selectAllButton2) => {
        selectAllButton2.classList.toggle('filter-off');
        selectAllButton2.classList.toggle('filter-on');
      });
      [...linkCheckboxes].forEach((linkCheckbox) => {
        linkCheckbox.checked = checked;
        linkCheckbox.dispatchEvent(new Event('change'));
      });
    });
  });

  /**
   * Tag list operations
   *
   * TODO: support error code in the backend for AJAX requests
   */
  const tagList = document.querySelector('input[name="taglist"]');
  let existingTags = tagList ? tagList.value.split(' ') : [];
  let awesomepletes = [];

  // Display/Hide rename form
  const renameTagButtons = document.querySelectorAll('.rename-tag');
  [...renameTagButtons].forEach((rename) => {
    rename.addEventListener('click', (event) => {
      event.preventDefault();
      const block = findParent(event.target, 'div', { class: 'tag-list-item' });
      const form = block.querySelector('.rename-tag-form');
      if (form.style.display === 'none' || form.style.display === '') {
        form.style.display = 'block';
      } else {
        form.style.display = 'none';
      }
      block.querySelector('input').focus();
    });
  });

  // Rename a tag with an AJAX request
  const renameTagSubmits = document.querySelectorAll('.validate-rename-tag');
  [...renameTagSubmits].forEach((rename) => {
    rename.addEventListener('click', (event) => {
      event.preventDefault();
      const block = findParent(event.target, 'div', { class: 'tag-list-item' });
      const input = block.querySelector('.rename-tag-input');
      const totag = input.value.replace('/"/g', '\\"');
      if (totag.trim() === '') {
        return;
      }
      const refreshedToken = document.getElementById('token').value;
      const fromtag = block.getAttribute('data-tag');
      const xhr = new XMLHttpRequest();
      xhr.open('POST', '?do=changetag');
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onload = () => {
        if (xhr.status !== 200) {
          alert(`An error occurred. Return code: ${xhr.status}`);
          location.reload();
        } else {
          block.setAttribute('data-tag', totag);
          input.setAttribute('name', totag);
          input.setAttribute('value', totag);
          findParent(input, 'div', { class: 'rename-tag-form' }).style.display = 'none';
          block.querySelector('a.tag-link').innerHTML = htmlEntities(totag);
          block.querySelector('a.tag-link').setAttribute('href', `?searchtags=${encodeURIComponent(totag)}`);
          block.querySelector('a.rename-tag').setAttribute('href', `?do=changetag&fromtag=${encodeURIComponent(totag)}`);

          // Refresh awesomplete values
          existingTags = existingTags.map(tag => (tag === fromtag ? totag : tag));
          awesomepletes = updateAwesompleteList('.rename-tag-input', existingTags, awesomepletes);
        }
      };
      xhr.send(`renametag=1&fromtag=${encodeURIComponent(fromtag)}&totag=${encodeURIComponent(totag)}&token=${refreshedToken}`);
      refreshToken();
    });
  });

  // Validate input with enter key
  const renameTagInputs = document.querySelectorAll('.rename-tag-input');
  [...renameTagInputs].forEach((rename) => {
    rename.addEventListener('keypress', (event) => {
      if (event.keyCode === 13) { // enter
        findParent(event.target, 'div', { class: 'tag-list-item' }).querySelector('.validate-rename-tag').click();
      }
    });
  });

  // Delete a tag with an AJAX query (alert popup confirmation)
  const deleteTagButtons = document.querySelectorAll('.delete-tag');
  [...deleteTagButtons].forEach((rename) => {
    rename.style.display = 'inline';
    rename.addEventListener('click', (event) => {
      event.preventDefault();
      const block = findParent(event.target, 'div', { class: 'tag-list-item' });
      const tag = block.getAttribute('data-tag');
      const refreshedToken = document.getElementById('token').value;

      if (confirm(`Are you sure you want to delete the tag "${tag}"?`)) {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '?do=changetag');
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = () => {
          block.remove();
        };
        xhr.send(encodeURI(`deletetag=1&fromtag=${tag}&token=${refreshedToken}`));
        refreshToken();

        existingTags = existingTags.filter(tagItem => tagItem !== tag);
        awesomepletes = updateAwesompleteList('.rename-tag-input', existingTags, awesomepletes);
      }
    });
  });

  const autocompleteFields = document.querySelectorAll('input[data-multiple]');
  [...autocompleteFields].forEach((autocompleteField) => {
    awesomepletes.push(createAwesompleteInstance(autocompleteField));
  });
})();
