(() => {
  document.addEventListener('DOMContentLoaded', () => {
    const unreadLinks = document.querySelectorAll('.readitlater-unread');
    if (unreadLinks) {
      const unreadLabel = document.createElement('span');
      unreadLabel.className = 'label label-unread';
      unreadLabel.innerHTML = ' To Read';
      [...unreadLinks].forEach((element) => {
        const button = unreadLabel.cloneNode(true);
        element.querySelector('.linklist-item-editbuttons').prepend(button);
      });
    }
  });
})();
