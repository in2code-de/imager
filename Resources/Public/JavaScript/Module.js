import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import DocumentService from '@typo3/core/document-service.js';
import Notification from '@typo3/backend/notification.js';

class ImagerModule {
  constructor() {
    this.baseToken = null;
    DocumentService.ready().then(() => this.initialize());
  }

  initialize() {
    this.root = document.querySelector('.imager-module');
    if (!this.root) {
      return;
    }
    this.folder = this.root.dataset.imagerFolder;
    this.writable = this.root.dataset.imagerWritable === '1';
    this.labels = this.root.dataset;
    this.promptEl = this.root.querySelector('[data-imager-prompt]');
    this.grid = this.root.querySelector('[data-imager-candidates-grid]');
    this.candidatesWrap = this.root.querySelector('[data-imager-candidates]');
    this.refineHint = this.root.querySelector('[data-imager-refinehint]');
    this.generateBtn = this.root.querySelector('[data-imager-action="generate"]');
    this.resetBtn = this.root.querySelector('[data-imager-action="resetRefine"]');

    this.generateBtn?.addEventListener('click', () => this.generate());
    this.resetBtn?.addEventListener('click', () => this.resetRefine());
  }

  async generate() {
    const prompt = (this.promptEl?.value || '').trim();
    if (!prompt) {
      return;
    }
    const url = TYPO3?.settings?.ajaxUrls?.imager_generate;
    if (!url) {
      return;
    }
    const originalLabel = this.generateBtn.innerHTML;
    this.generateBtn.disabled = true;
    this.generateBtn.textContent = this.labels.labelGenerating;
    try {
      const response = await new AjaxRequest(url).post({
        prompt,
        folder: this.folder,
        baseToken: this.baseToken || '',
      });
      const data = await response.resolve();
      if (data?.success) {
        this.renderCandidates(data.candidates || []);
      } else {
        Notification.error(this.labels.labelError, data?.error || '');
      }
    } catch (error) {
      Notification.error(this.labels.labelError, error?.message || '');
    } finally {
      this.generateBtn.disabled = false;
      this.generateBtn.innerHTML = originalLabel;
    }
  }

  renderCandidates(candidates) {
    this.grid.replaceChildren();
    candidates.forEach((candidate) => this.grid.appendChild(this.buildTile(candidate)));
    this.candidatesWrap.hidden = candidates.length === 0;
  }

  buildTile(candidate) {
    const tile = document.createElement('div');
    tile.className = 'imager-tile imager-tile--candidate';
    tile.dataset.token = candidate.token;

    const image = document.createElement('img');
    image.src = candidate.dataUri;
    image.loading = 'lazy';
    tile.appendChild(image);

    const actions = document.createElement('div');
    actions.className = 'imager-tile__actions';

    if (this.writable) {
      const saveBtn = document.createElement('button');
      saveBtn.type = 'button';
      saveBtn.className = 'btn btn-sm btn-primary';
      saveBtn.textContent = this.labels.labelSave;
      saveBtn.addEventListener('click', () => this.save(candidate.token));
      actions.appendChild(saveBtn);
    }

    const refineBtn = document.createElement('button');
    refineBtn.type = 'button';
    refineBtn.className = 'btn btn-sm btn-default';
    refineBtn.textContent = this.labels.labelRefine;
    refineBtn.addEventListener('click', () => this.selectForRefine(candidate.token, tile));
    actions.appendChild(refineBtn);

    tile.appendChild(actions);
    return tile;
  }

  async save(token) {
    if (!this.writable) {
      return;
    }
    const url = TYPO3?.settings?.ajaxUrls?.imager_save;
    if (!url) {
      return;
    }
    try {
      const response = await new AjaxRequest(url).post({ candidate: token, folder: this.folder });
      const data = await response.resolve();
      if (data?.success) {
        Notification.success(this.labels.labelSaved, data.fileName || '');
        window.location.reload();
      } else {
        Notification.error(this.labels.labelSaveerror, data?.error || '');
      }
    } catch (error) {
      Notification.error(this.labels.labelSaveerror, error?.message || '');
    }
  }

  selectForRefine(token, tile) {
    this.baseToken = token;
    this.grid.querySelectorAll('.imager-tile--selected').forEach((element) => {
      element.classList.remove('imager-tile--selected');
    });
    tile.classList.add('imager-tile--selected');
    this.refineHint.textContent = this.labels.labelRefineactive;
    this.refineHint.hidden = false;
    this.resetBtn.hidden = false;
    this.promptEl?.focus();
  }

  resetRefine() {
    this.baseToken = null;
    this.refineHint.hidden = true;
    this.resetBtn.hidden = true;
    this.grid.querySelectorAll('.imager-tile--selected').forEach((element) => {
      element.classList.remove('imager-tile--selected');
    });
  }
}

export default new ImagerModule();
