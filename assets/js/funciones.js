function setupTablePagination(tableSelector, paginationSelector, searchSelector) {
    const tableBody = document.querySelector(tableSelector);
    if (!tableBody) return;

    const allRows = Array.from(tableBody.getElementsByTagName('tr'));
    const paginationContainer = document.getElementById(paginationSelector);
    const searchInput = document.getElementById(searchSelector);
    const rowsPerPage = 10;
    let currentPage = 1;
    let filteredRows = [...allRows];

    function displayRows() {
        allRows.forEach(row => row.style.display = 'none');
        const start = (currentPage - 1) * rowsPerPage;
        const end = start + rowsPerPage;
        filteredRows.slice(start, end).forEach(row => row.style.display = '');
    }

    function setupPagination() {
        paginationContainer.innerHTML = '';
        const totalPages = Math.ceil(filteredRows.length / rowsPerPage);

        if (totalPages <= 1) {
            paginationContainer.style.display = 'none';
            return;
        }

        paginationContainer.style.display = 'flex';

        const createPageItem = (label, page, isDisabled = false, isActive = false) => {
            const li = document.createElement('li');
            li.className = `page-item ${isDisabled ? 'disabled' : ''} ${isActive ? 'active' : ''}`;
            const a = document.createElement('a');
            a.className = 'page-link';
            a.href = '#';
            a.textContent = label;
            if (page) a.dataset.page = page;
            li.appendChild(a);
            return li;
        };

        const totalPagesToShow = 5;
        let startPage = Math.max(1, currentPage - Math.floor(totalPagesToShow / 2));
        let endPage = Math.min(totalPages, startPage + totalPagesToShow - 1);
        if(endPage - startPage + 1 < totalPagesToShow) {
            startPage = Math.max(1, endPage - totalPagesToShow + 1);
        }

        paginationContainer.appendChild(createPageItem('Anterior', currentPage - 1, currentPage === 1));

        if (startPage > 1) {
            paginationContainer.appendChild(createPageItem('1', 1));
            if (startPage > 2) {
                paginationContainer.appendChild(createPageItem('...', null, true));
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            paginationContainer.appendChild(createPageItem(i, i, false, i === currentPage));
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                paginationContainer.appendChild(createPageItem('...', null, true));
            }
            paginationContainer.appendChild(createPageItem(totalPages, totalPages));
        }


        paginationContainer.appendChild(createPageItem('Siguiente', currentPage + 1, currentPage === totalPages));

        paginationContainer.addEventListener('click', e => {
            if (e.target.tagName === 'A' && e.target.dataset.page) {
                e.preventDefault();
                const newPage = parseInt(e.target.dataset.page, 10);
                if (!isNaN(newPage) && newPage >= 1 && newPage <= totalPages) {
                    currentPage = newPage;
                    // Redraw rows and pagination
                    displayRows();
                    setupPagination();
                }
            }
        });
    }

    function filterRows() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
        filteredRows = allRows.filter(row => {
            if (!searchTerm) return true;
            // Search in all cells except the action cell
            const cells = Array.from(row.getElementsByTagName('td'));
            return cells.some(cell => cell.textContent.toLowerCase().includes(searchTerm));
        });
        currentPage = 1;
        displayRows();
        setupPagination();
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterRows);
    }

    // Initial setup
    filterRows();
}
