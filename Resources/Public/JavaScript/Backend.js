import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import DocumentService from '@typo3/core/document-service.js';

class ImagerBackend {
  constructor() {
    DocumentService.ready().then(() => this.buttonListener());
  }

  buttonListener() {
    const buttons = document.querySelectorAll('[data-imager-action="showPrompt"]');
    buttons.forEach(button => {
      button.addEventListener('click', () => {
        this.showTextarea(button);
      });
    });

    const addImageButtons = document.querySelectorAll('[data-imager-action="addImage"]');
    addImageButtons.forEach(button => {
      button.addEventListener('click', async () => {
        await this.addImageWithPrompt(button);
      });
    });
  }

  showTextarea(button) {
    const container = button.parentElement.querySelector('[data-imager-textarea-container]');
    if (container) {
      container.style.display = 'block';
    }
  }

  async addImageWithPrompt(button) {
    const table = button.getAttribute('data-imager-tablename');
    const uid = parseInt(button.getAttribute('data-imager-uid') || '0', 10);
    const field = button.getAttribute('data-imager-fieldname');
    const pid = parseInt(button.getAttribute('data-imager-pid') || '0', 10);
    const textarea = button.parentElement.querySelector('[data-imager-prompt]');
    const prompt = textarea?.value || '';

    if (!table || !uid || !field) {
      console.error('Imager: Missing required data attributes');
      return;
    }
    if (!prompt.trim()) {
      console.error('Imager: Prompt text is required');
      return;
    }
    const url = TYPO3?.settings?.ajaxUrls?.imager_getimage;
    if (!url) {
      console.error('Imager: AJAX route "imager_getimage" not available');
      return;
    }

    button.disabled = true;
    button.classList.add('disabled');

    try {
      const response = await new AjaxRequest(url).post({ table, uid, field, pid, prompt });
      const data = await response.resolve();
      if (data?.success) {
        // Reload current form to reflect new file reference
        window.location.reload();
      } else {
        console.error('Imager: Adding image with prompt failed', data);
        button.disabled = false;
        button.classList.remove('disabled');
      }
    } catch (e) {
      console.error('Imager: AJAX error', e);
      button.disabled = false;
      button.classList.remove('disabled');
    }
  }

  async addDummyImage(button) {
    const table = button.getAttribute('data-imager-tablename');
    const uid = parseInt(button.getAttribute('data-imager-uid') || '0', 10);
    const field = button.getAttribute('data-imager-fieldname');
    const pid = parseInt(button.getAttribute('data-imager-pid') || '0', 10);
    if (!table || !uid || !field) {
      console.error('Imager: Missing required data attributes');
      return;
    }
    const url = TYPO3?.settings?.ajaxUrls?.imager_getimage;
    if (!url) {
      console.error('Imager: AJAX route "imager_getimage" not available');
      return;
    }

    button.disabled = true;
    button.classList.add('disabled');

    try {
      const response = await new AjaxRequest(url).post({ table, uid, field, pid });
      const data = await response.resolve();
      if (data?.success) {
        // Reload current form to reflect new file reference
        window.location.reload();
      } else {
        console.error('Imager: Adding dummy image failed', data);
        button.disabled = false;
        button.classList.remove('disabled');
      }
    } catch (e) {
      console.error('Imager: AJAX error', e);
      button.disabled = false;
      button.classList.remove('disabled');
    }
  }
}

export default new ImagerBackend();
