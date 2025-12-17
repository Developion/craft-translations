;(() => {
	const ready = fn => {
		if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn)
		else fn()
	}

	const debounce = (fn, wait = 120) => {
		let t
		return (...args) => {
			clearTimeout(t)
			t = setTimeout(() => fn(...args), wait)
		}
	}

	ready(() => {
		const searchInput = document.querySelector('#search') || document.querySelector('#search input[type="text"]')

		const tableRoot = document.querySelector('#translations-field') || document.querySelector('#translations')

		if (!searchInput || !tableRoot) return

		const findRows = () => {
			const table = tableRoot.querySelector('table') || tableRoot
			return Array.from(table.querySelectorAll('tbody tr'))
		}

		const getOriginalValue = row => {
			const byClass = row.querySelector(
				'.js-translation-search input, input.js-translation-search, .js-translation-search',
			)
			if (byClass) {
				if (byClass.matches('input, textarea, select')) return (byClass.value || '').toLowerCase()
				return (byClass.textContent || '').toLowerCase()
			}

			const byName = row.querySelector(
				'input[name$="[original]"], textarea[name$="[original]"], select[name$="[original]"]',
			)
			if (byName) return (byName.value || '').toLowerCase()

			const firstCell = row.querySelector('td')
			if (!firstCell) return ''
			const inputInFirstCell = firstCell.querySelector('input, textarea, select')
			return ((inputInFirstCell ? inputInFirstCell.value : firstCell.textContent) || '').toLowerCase()
		}

		const applyFilter = () => {
			const q = (searchInput.value || '').trim().toLowerCase()
			const rows = findRows()

			rows.forEach(row => {
				if (!q) {
					row.style.display = ''
					return
				}
				const sourceVal = getOriginalValue(row)
				row.style.display = sourceVal.includes(q) ? '' : 'none'
			})
		}

		const onChange = debounce(applyFilter, 120)
		searchInput.addEventListener('input', onChange)
		searchInput.addEventListener('change', onChange)

		applyFilter()
	})
})()
