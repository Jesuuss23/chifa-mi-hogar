function createTrackingGpsGuard({ clear, notify, now, setTimeout, clearTimeout }) {
    let generation = 0;
    let timer = null;
    function invalidate(message) {
        generation++;
        clearTimeout(timer);
        timer = null;
        clear();
        if (message) notify(message);
    }
    function validPoint(point) {
        return point && typeof point.latitude === 'number' && typeof point.longitude === 'number'
            && Number.isFinite(point.latitude) && Number.isFinite(point.longitude)
            && Math.abs(point.latitude) <= 90 && Math.abs(point.longitude) <= 180;
    }
    function accept(result, ticket) {
        if (ticket !== generation) return false;
        const timestamp = typeof result?.location_updated_at === 'string'
            ? Date.parse(result.location_updated_at) : NaN;
        const age = now() - timestamp;
        if (!result || result.gps_fresh !== true || result.gps_active !== true
            || !validPoint(result.driver) || !validPoint(result.destination)
            || !Number.isFinite(age) || age < 0 || age >= 60000) {
            invalidate('Esperando una ubicación GPS reciente y válida del repartidor.');
            return false;
        }
        clearTimeout(timer);
        timer = setTimeout(() => invalidate('La ubicación GPS dejó de estar actualizada. Esperando una nueva señal.'), 60000 - age);
        return true;
    }
    return { invalidate, accept, ticket: () => generation, current: ticket => ticket === generation };
}
