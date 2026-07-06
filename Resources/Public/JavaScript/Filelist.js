import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import DocumentService from '@typo3/core/document-service.js';
import Notification from '@typo3/backend/notification.js';

/**
 * Adds an "AI area" on top of the core file list module. Toggled via the "Show AI area" item in the
 * "View" dropdown; state is persisted per backend user through the imager_toggle AJAX route.
 */
class ImagerFilelist {
  constructor() {
    this.baseToken = null;
    DocumentService.ready().then(() => this.initialize());
  }

  get settings() {
    return (typeof TYPO3 !== 'undefined' && TYPO3.settings && TYPO3.settings.imager) || {};
  }

  label(key) {
    return (typeof TYPO3 !== 'undefined' && TYPO3.lang && TYPO3.lang[key]) || key;
  }

  ajaxUrl(route) {
    return (typeof TYPO3 !== 'undefined' && TYPO3.settings && TYPO3.settings.ajaxUrls) ? TYPO3.settings.ajaxUrls[route] : null;
  }

  initialize() {
    this.toggle = document.querySelector('[data-imager-ai-toggle]');
    if (!this.toggle) {
      return;
    }
    this.writable = this.settings.writable === '1';
    this.toggle.addEventListener('click', (event) => {
      event.preventDefault();
      this.toggleArea();
    });
    if (this.settings.showAiArea === '1') {
      this.showArea();
    }
  }

  toggleArea() {
    const enabled = this.isAreaVisible() === false;
    if (enabled) {
      this.showArea();
    } else {
      this.hideArea();
    }
    this.persist(enabled);
  }

  isAreaVisible() {
    const area = document.getElementById('imager-ai-area');
    return area !== null && area.hidden === false;
  }

  setToggleActive(active) {
    this.toggle.dataset.dropdowntoggleStatus = active ? 'active' : 'inactive';
  }

  persist(enabled) {
    const url = this.ajaxUrl('imager_toggle');
    if (url) {
      new AjaxRequest(url).post({ enabled: enabled ? '1' : '0' }).catch(() => {});
    }
  }

  showArea() {
    let area = document.getElementById('imager-ai-area');
    if (area === null) {
      area = this.buildArea();
      this.insertArea(area);
    }
    area.hidden = false;
    this.setToggleActive(true);
  }

  hideArea() {
    const area = document.getElementById('imager-ai-area');
    if (area !== null) {
      area.hidden = true;
    }
    this.setToggleActive(false);
  }

  insertArea(area) {
    const anchor = document.querySelector('.filelist-main');
    if (anchor && anchor.parentNode) {
      anchor.parentNode.insertBefore(area, anchor);
    } else {
      const body = document.querySelector('.module-body, .t3js-module-body');
      if (body) {
        body.prepend(area);
      }
    }
  }

  currentFolder() {
    const element = document.querySelector('[data-filelist-current-identifier]');
    if (element && element.dataset.filelistCurrentIdentifier) {
      return element.dataset.filelistCurrentIdentifier;
    }
    return new URLSearchParams(window.location.search).get('id') || '';
  }

  buildArea() {
    const area = document.createElement('div');
    area.id = 'imager-ai-area';
    area.className = 'imager-ai-area';
    area.hidden = true;

    this.candidatesWrap = document.createElement('div');
    this.candidatesWrap.className = 'imager-ai-area__candidates';
    this.candidatesWrap.hidden = true;
    const candidatesHeadline = document.createElement('h3');
    candidatesHeadline.textContent = this.label('module.candidates.headline');
    this.grid = document.createElement('div');
    this.grid.className = 'imager-grid';
    this.candidatesWrap.append(candidatesHeadline, this.grid);

    const controls = document.createElement('div');
    controls.className = 'imager-ai-area__controls';
    const promptLabel = document.createElement('label');
    promptLabel.className = 'form-label';
    promptLabel.textContent = this.label('module.prompt.label');
    this.promptEl = document.createElement('textarea');
    this.promptEl.className = 'form-control';
    this.promptEl.rows = 3;
    this.promptEl.value = this.settings.defaultPrompt || '';
    this.promptEl.placeholder = this.settings.promptPlaceholder || '';
    this.refineHint = document.createElement('div');
    this.refineHint.className = 'imager-ai-area__refinehint';
    this.refineHint.hidden = true;
    const buttons = document.createElement('div');
    buttons.className = 'imager-ai-area__buttons';
    this.generateBtn = document.createElement('button');
    this.generateBtn.type = 'button';
    this.generateBtn.className = 'btn btn-primary';
    this.generateBtn.textContent = this.label('module.generate');
    this.generateBtn.addEventListener('click', () => this.generate());
    this.resetBtn = document.createElement('button');
    this.resetBtn.type = 'button';
    this.resetBtn.className = 'btn btn-default';
    this.resetBtn.textContent = this.label('module.refine.reset');
    this.resetBtn.hidden = true;
    this.resetBtn.addEventListener('click', () => this.resetRefine());
    buttons.append(this.generateBtn, this.resetBtn);
    controls.append(promptLabel, this.promptEl, this.refineHint, buttons);

    area.append(this.candidatesWrap, controls);
    return area;
  }

  async generate() {
    const prompt = (this.promptEl.value || '').trim();
    const url = this.ajaxUrl('imager_generate');
    if (!prompt || !url) {
      return;
    }
    const originalLabel = this.generateBtn.textContent;
    this.generateBtn.disabled = true;
    this.generateBtn.textContent = this.label('module.generating');
    try {
      const response = await new AjaxRequest(url).post({
        prompt,
        folder: this.currentFolder(),
        baseToken: this.baseToken || '',
      });
      const data = await response.resolve();
      if (data?.success) {
        this.renderCandidates(data.candidates || []);
      } else {
        Notification.error(this.label('module.error'), data?.error || '');
      }
    } catch (error) {
      Notification.error(this.label('module.error'), error?.message || '');
    } finally {
      this.generateBtn.disabled = false;
      this.generateBtn.textContent = originalLabel;
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
      saveBtn.textContent = this.label('module.candidate.save');
      saveBtn.addEventListener('click', () => this.save(candidate.token));
      actions.appendChild(saveBtn);
    }

    const refineBtn = document.createElement('button');
    refineBtn.type = 'button';
    refineBtn.className = 'btn btn-sm btn-default';
    refineBtn.textContent = this.label('module.candidate.refine');
    refineBtn.addEventListener('click', () => this.selectForRefine(candidate.token, tile));
    actions.appendChild(refineBtn);

    tile.appendChild(actions);
    return tile;
  }

  async save(token) {
    const url = this.ajaxUrl('imager_save');
    if (!this.writable || !url) {
      return;
    }
    try {
      const response = await new AjaxRequest(url).post({ candidate: token, folder: this.currentFolder() });
      const data = await response.resolve();
      if (data?.success) {
        Notification.success(this.label('module.saved'), data.fileName || '');
        window.location.reload();
      } else {
        Notification.error(this.label('module.saveError'), data?.error || '');
      }
    } catch (error) {
      Notification.error(this.label('module.saveError'), error?.message || '');
    }
  }

  selectForRefine(token, tile) {
    this.baseToken = token;
    this.grid.querySelectorAll('.imager-tile--selected').forEach((element) => {
      element.classList.remove('imager-tile--selected');
    });
    tile.classList.add('imager-tile--selected');
    this.refineHint.textContent = this.label('module.refine.active');
    this.refineHint.hidden = false;
    this.resetBtn.hidden = false;
    this.promptEl.focus();
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

export default new ImagerFilelist();
