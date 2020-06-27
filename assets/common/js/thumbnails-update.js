/**
 * Script used in the thumbnails update page.
 *
 * It retrieves the list of link IDs to update, and execute AJAX requests
 * to update their thumbnails, while updating the progress bar.
 */

/**
 * Update the thumbnail of the link with the current i index in ids.
 * It contains a recursive call to retrieve the thumb of the next link when it succeed.
 * It also update the progress bar and other visual feedback elements.
 *
 * @param {string} basePath Shaarli subfolder for XHR requests
 * @param {array}  ids      List of LinkID to update
 * @param {int}    i        Current index in ids
 * @param {object} elements List of DOM element to avoid retrieving them at each iteration
 */
function updateThumb(basePath, ids, i, elements) {
  const xhr = new XMLHttpRequest();
  xhr.open('PATCH', `${basePath}/admin/shaare/${ids[i]}/update-thumbnail`);
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhr.responseType = 'json';
  xhr.onload = () => {
    if (xhr.status !== 200) {
      alert(`An error occurred. Return code: ${xhr.status}`);
    } else {
      const { response } = xhr;
      i += 1;
      elements.progressBar.style.width = `${(i * 100) / ids.length}%`;
      elements.current.innerHTML = i;
      elements.title.innerHTML = response.title;
      if (response.thumbnail !== false) {
        elements.thumbnail.innerHTML = `<img src="${basePath}/${response.thumbnail}">`;
      }
      if (i < ids.length) {
        updateThumb(basePath, ids, i, elements);
      }
    }
  };
  xhr.send();
}

(() => {
  const basePath = document.querySelector('input[name="js_base_path"]').value;
  const ids = document.getElementsByName('ids')[0].value.split(',');
  const elements = {
    progressBar: document.querySelector('.progressbar > div'),
    current: document.querySelector('.progress-current'),
    thumbnail: document.querySelector('.thumbnail-placeholder'),
    title: document.querySelector('.thumbnail-link-title'),
  };
  updateThumb(basePath, ids, 0, elements);
})();
