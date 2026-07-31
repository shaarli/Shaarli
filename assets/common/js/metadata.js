import he from 'he';

/**
 * This script is used to retrieve bookmarks metadata asynchronously:
 *    - title, description and keywords while creating a new bookmark
 *    - thumbnails while visiting the bookmark list
 *
 * Note: it should only be included if the user is logged in
 *       and the setting general.enable_async_metadata is enabled.
 */

/**
 * Removes given input loaders - used in edit link template.
 *
 * @param {object} loaders List of input DOM element that need to be cleared
 */
function clearLoaders(loaders) {
  if (loaders != null && loaders.length > 0) {
    [...loaders].forEach((loader) => {
      loader.classList.remove('loading-input');
    });
  }
}

/**
 * AJAX request to update the thumbnail of a bookmark with the provided ID.
 * If a thumbnail is retrieved, it updates the divElement with the image src, and displays it.
 *
 * @param {string} basePath   Shaarli subfolder for XHR requests
 * @param {object} divElement Main <div> DOM element containing the thumbnail placeholder
 * @param {int}    id         Bookmark ID to update
 */
function updateThumb(basePath, divElement, id) {
  const xhr = new XMLHttpRequest();
  xhr.open('PATCH', `${basePath}/admin/shaare/${id}/update-thumbnail`);
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhr.responseType = 'json';
  xhr.onload = () => {
    if (xhr.status !== 200) {
      alert(`An error occurred. Return code: ${xhr.status}`);
    } else {
      const { response } = xhr;

      if (response.thumbnail !== false) {
        const imgElement = divElement.querySelector('img');

        imgElement.src = response.thumbnail;
        imgElement.dataset.src = response.thumbnail;
        imgElement.style.opacity = '1';
        divElement.classList.remove('hidden');
      }
    }
  };
  xhr.send();
}

(() => {
  const basePath = document.querySelector('input[name="js_base_path"]').value;
  const token = document.querySelector('#token')?.value || '';

  /*
   * METADATA FOR EDIT BOOKMARK PAGE
   */
  const inputTitles = document.querySelectorAll('input[name="lf_title"]');
  if (inputTitles != null) {
    [...inputTitles].forEach((inputTitle) => {
      const form = inputTitle.closest('form[name="linkform"]');
      const loaders = form.querySelectorAll('.loading-input');

      if (inputTitle.value.length > 0) {
        clearLoaders(loaders);
        return;
      }

      const url = form.querySelector('input[name="lf_url"]').value;

      const xhr = new XMLHttpRequest();
      xhr.open('GET', `${basePath}/admin/metadata?url=${encodeURI(url)}&token=${encodeURIComponent(token)}`, true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onload = () => {
        const result = JSON.parse(xhr.response);
        Object.keys(result).forEach((key) => {
          if (result[key] !== null && result[key].length) {
            const element = form.querySelector(`input[name="lf_${key}"], textarea[name="lf_${key}"]`);
            if (element != null && element.value.length === 0) {
              element.value = he.decode(result[key]);
            }
          }
        });
        clearLoaders(loaders);
      };

      xhr.send();
    });
  }

  /*
   * METADATA FOR THUMBNAIL RETRIEVAL
   */
  const thumbsToLoad = document.querySelectorAll('div[data-async-thumbnail]');
  if (thumbsToLoad != null) {
    [...thumbsToLoad].forEach((divElement) => {
      const { id } = divElement.closest('[data-id]').dataset;

      updateThumb(basePath, divElement, id);
    });
  }
})();
